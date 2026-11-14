<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;

final class ActaPdfService
{
    public static function build(array $election,array $summary,string $sha256): string
    {
        $document=new ActaPdfDocument();
        $document->render($election,$summary,$sha256);
        Audit::log('acta.pdf_generated','election',(int)$election['id'],['sha256'=>$sha256]);
        return $document->output();
    }
}

final class ActaPdfDocument
{
    private const PAGE_W=595.28;
    private const PAGE_H=841.89;
    private const MARGIN=48.0;
    private const NAVY=[0.059,0.165,0.267];
    private const BLUE=[0.122,0.420,0.549];
    private const LIGHT_BLUE=[0.925,0.965,0.980];
    private const ORANGE=[1.0,0.478,0.0];
    private const TEAL=[0.122,0.549,0.537];
    private const INK=[0.075,0.165,0.243];
    private const MUTED=[0.40,0.47,0.54];
    private const LINE=[0.87,0.91,0.94];
    private const PALE=[0.965,0.976,0.984];

    private array $pages=[];
    private int $page=0;
    private float $cursor=0;

    public function render(array $election,array $summary,string $sha256): void
    {
        $total=(int)($summary['participation']['total']??0);
        $voted=(int)($summary['participation']['voted']??0);
        $participation=$total>0?round($voted*100/$total,1):0;
        $this->newPage();
        $this->brandHeader();
        $this->text(48,132,'ACTA DE RESULTADOS',23,'B',self::NAVY);
        $this->text(48,157,(string)$election['title'],13,'B',self::BLUE);
        $this->text(48,177,'Documento oficial de cierre y consolidación del proceso electoral',9,'R',self::MUTED);
        $this->metadata($election);
        $this->statistics($total,$voted,$participation);
        $this->cursor=385;

        foreach(($summary['questions']??[]) as $index=>$question){
            $height=58+count($question['options']??[])*38;
            if($this->cursor+$height>690)$this->continuationPage();
            $this->questionBlock($index+1,$question);
        }

        if($this->cursor+150>735)$this->continuationPage();
        $this->integrityBlock($sha256);
        $this->signatureBlock();
        $this->addFooters((int)$election['id']);
    }

    public function output(): string
    {
        $objects=[];
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids=[];$objectNumber=5;
        foreach($this->pages as $content){
            $pageObject=$objectNumber++;$contentObject=$objectNumber++;
            $kids[]=$pageObject.' 0 R';
            $objects[$pageObject]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_W.' '.self::PAGE_H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject]='<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $number=>$object){$offsets[$number]=strlen($pdf);$pdf.=$number." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$size=max(array_keys($objects))+1;
        $pdf.="xref\n0 {$size}\n0000000000 65535 f \n";
        for($i=1;$i<$size;$i++)$pdf.=isset($offsets[$i])?str_pad((string)$offsets[$i],10,'0',STR_PAD_LEFT)." 00000 n \n":"0000000000 00000 f \n";
        return $pdf.'trailer << /Size '.$size.' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";
    }

    private function newPage(): void{$this->pages[]='';$this->page=count($this->pages)-1;$this->cursor=48;}
    private function continuationPage(): void{$this->newPage();$this->brandHeader(true);$this->cursor=132;}
    private function command(string $command): void{$this->pages[$this->page].=$command."\n";}
    private function y(float $top): float{return self::PAGE_H-$top;}
    private function color(array $color,bool $stroke=false): string{return implode(' ',array_map(static fn($v)=>rtrim(rtrim(number_format($v,3,'.',''),'0'),'.'),$color)).($stroke?' RG':' rg');}

    private function text(float $x,float $top,string $text,float $size=10,string $weight='R',array $color=self::INK): void
    {
        $font=$weight==='B'?'/F2':'/F1';$escaped=$this->pdfText($text);
        $this->command('BT '.$this->color($color).' '.$font.' '.number_format($size,2,'.','').' Tf '.number_format($x,2,'.','').' '.number_format($this->y($top),2,'.','').' Td ('.$escaped.') Tj ET');
    }

    private function rect(float $x,float $top,float $width,float $height,array $fill,?array $stroke=null,float $lineWidth=1): void
    {
        $command='q '.$this->color($fill).' ';
        if($stroke!==null)$command.=$this->color($stroke,true).' '.number_format($lineWidth,2,'.','').' w ';
        $command.=number_format($x,2,'.','').' '.number_format($this->y($top+$height),2,'.','').' '.number_format($width,2,'.','').' '.number_format($height,2,'.','').' re '.($stroke!==null?'B':'f').' Q';$this->command($command);
    }

    private function line(float $x1,float $top1,float $x2,float $top2,array $color,float $width=1): void{$this->command('q '.$this->color($color,true).' '.$width.' w '.$x1.' '.$this->y($top1).' m '.$x2.' '.$this->y($top2).' l S Q');}

    private function brandHeader(bool $compact=false): void
    {
        $this->rect(0,0,self::PAGE_W,$compact?84:96,self::NAVY);
        $this->logoMark(48,$compact?21:25,45);
        $this->text(105,$compact?46:52,'QUORUM',21,'B',[1,1,1]);
        $this->text(106,$compact?65:72,'DECISIONES DIGITALES VERIFICABLES',6.8,'B',[0.72,0.83,0.90]);
        $this->text(475,$compact?48:54,$compact?'ACTA - CONTINUACIÓN':'ACTA OFICIAL',8,'B',[1,1,1]);
        $this->rect(0,$compact?84:96,self::PAGE_W,4,self::ORANGE);
    }

    private function logoMark(float $x,float $top,float $size): void
    {
        $cx=$x+$size/2;$cy=$this->y($top+$size/2);$r=$size*.35;$k=.55228475;$blue=self::BLUE;$navy=self::NAVY;
        $this->command('q '.$this->color($blue,true).' 5.5 w 1 J '.$cx.' '.($cy+$r).' m '.($cx+$k*$r).' '.($cy+$r).' '.($cx+$r).' '.($cy+$k*$r).' '.($cx+$r).' '.$cy.' c S Q');
        $this->command('q '.$this->color([0.23,0.63,0.78],true).' 5.5 w 1 J '.($cx-$r).' '.$cy.' m '.($cx-$r).' '.($cy+$k*$r).' '.($cx-$k*$r).' '.($cy+$r).' '.$cx.' '.($cy+$r).' c S Q');
        $this->command('q '.$this->color($blue,true).' 5.5 w 1 J '.($cx-$r).' '.$cy.' m '.($cx-$r).' '.($cy-$k*$r).' '.($cx-$k*$r).' '.($cy-$r).' '.$cx.' '.($cy-$r).' c S Q');
        $this->command('q '.$this->color($navy,true).' 5.5 w 1 J '.$cx.' '.($cy-$r).' m '.($cx+$k*$r).' '.($cy-$r).' '.($cx+$r).' '.($cy-$k*$r).' '.($cx+$r).' '.$cy.' c S Q');
        $this->command('q '.$this->color(self::ORANGE,true).' 5.5 w 1 J '.($cx+$r*.45).' '.($cy-$r*.45).' m '.($cx+$r*1.12).' '.($cy-$r*1.12).' l S Q');
    }

    private function metadata(array $election): void
    {
        $this->rect(48,204,499,92,self::PALE,self::LINE);
        $items=[['ORGANIZACIÓN',(string)$election['organization_name']],['ESTADO','Terminada'],['INICIO',$this->date((string)($election['starts_at']??''))],['CIERRE',$this->date((string)($election['ends_at']??''))],['MODALIDAD',($election['voting_mode']??'secret')==='secret'?'Voto secreto':'Voto público'],['GENERACIÓN',date('d-m-Y H:i')]];
        foreach($items as $i=>$item){$column=$i%3;$row=intdiv($i,3);$x=64+$column*163;$top=226+$row*38;$this->text($x,$top,$item[0],6.8,'B',self::MUTED);$this->text($x,$top+15,$item[1],9.2,'B',self::INK);}
    }

    private function statistics(int $total,int $voted,float $participation): void
    {
        $values=[['HABILITADOS',(string)$total,self::BLUE],['VOTOS EMITIDOS',(string)$voted,self::TEAL],['PARTICIPACIÓN',number_format($participation,1,',','.').'%',self::ORANGE]];
        foreach($values as $i=>$item){$x=48+$i*171;$this->rect($x,314,158,54,[1,1,1],self::LINE);$this->rect($x,314,5,54,$item[2]);$this->text($x+17,334,$item[0],6.8,'B',self::MUTED);$this->text($x+17,355,$item[1],17,'B',$item[2]);}
    }

    private function questionBlock(int $number,array $question): void
    {
        $this->text(48,$this->cursor,'RESULTADOS - PREGUNTA '.str_pad((string)$number,2,'0',STR_PAD_LEFT),7.2,'B',self::BLUE);
        $this->cursor+=18;
        foreach($this->wrap((string)$question['text'],78) as $line){$this->text(48,$this->cursor,$line,12,'B',self::NAVY);$this->cursor+=15;}
        $this->cursor+=8;
        $options=$question['options']??[];$max=0;foreach($options as $option)$max=max($max,(float)$option['weighted_votes']);
        foreach($options as $option){
            $votes=(int)$option['votes'];$weighted=(float)$option['weighted_votes'];$width=$max>0?365*$weighted/$max:0;
            $this->text(60,$this->cursor,(string)$option['label'],9.2,'B',self::INK);
            $this->text(455,$this->cursor,$votes.' voto'.($votes===1?'':'s'),8.5,'B',self::MUTED);
            $this->rect(60,$this->cursor+9,365,6,self::LINE);if($width>0)$this->rect(60,$this->cursor+9,$width,6,self::TEAL);
            $this->text(435,$this->cursor+15,'Peso '.number_format($weighted,2,',','.'),6.8,'R',self::MUTED);
            $this->cursor+=35;
        }
        $this->cursor+=12;
    }

    private function integrityBlock(string $sha256): void
    {
        $this->rect(48,$this->cursor,499,94,self::LIGHT_BLUE,[0.73,0.84,0.89]);
        $this->text(64,$this->cursor+21,'INTEGRIDAD Y TRAZABILIDAD',7.2,'B',self::BLUE);
        $this->text(64,$this->cursor+39,'Huella digital SHA-256 de los resultados certificados',9,'B',self::NAVY);
        $this->rect(64,$this->cursor+49,467,20,[1,1,1]);
        $this->text(72,$this->cursor+63,$sha256,7.2,'R',self::INK);
        $verifyUrl=rtrim((string)(getenv('APP_URL')?:''),'/').'/verify.php';
        $this->text(64,$this->cursor+82,'Verifique esta huella en: '.$verifyUrl,7.2,'B',self::BLUE);
        $this->cursor+=112;
    }

    private function signatureBlock(): void
    {
        $top=max($this->cursor+18,690);
        if($top>745){$this->continuationPage();$top=190;}
        $this->line(65,$top,245,$top,self::LINE);$this->line(350,$top,530,$top,self::LINE);
        $this->text(107,$top+17,'RESPONSABLE DEL PROCESO',7,'B',self::MUTED);
        $this->text(386,$top+17,'MINISTRO DE FE',7,'B',self::MUTED);
        $this->text(104,$top+32,'Nombre y firma',7.5,'R',self::MUTED);
        $this->text(397,$top+32,'Nombre y firma',7.5,'R',self::MUTED);
    }

    private function addFooters(int $electionId): void
    {
        $total=count($this->pages);
        foreach(array_keys($this->pages) as $index){$this->page=$index;$this->line(48,796,547,796,self::LINE);$this->text(48,814,'QUORUM by INAUDIS - Documento verificable',7,'R',self::MUTED);$this->text(390,814,'Proceso #'.$electionId.'  |  Página '.($index+1).' de '.$total,7,'R',self::MUTED);}
    }

    private function date(string $value): string{if($value==='')return 'No informada';$timestamp=strtotime($value);return $timestamp?date('d-m-Y H:i',$timestamp):$value;}
    private function wrap(string $text,int $width): array{return explode("\n",wordwrap(trim($text),$width,"\n",true));}
    private function pdfText(string $text): string
    {
        $text=str_replace(["\u{2010}","\u{2011}","\u{2012}","\u{2013}","\u{2014}"],'-',$text);
        $converted=iconv('UTF-8','Windows-1252//TRANSLIT',$text);if($converted===false)$converted=$text;
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$converted);
    }
}
