<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;

final class ActaPdfService
{
    public static function build(array $election, array $summary, string $sha256): string
    {
        $lines=[];
        $lines[]='INAUDIS Vote - Acta de resultados';
        $lines[]='Proceso: '.(string)$election['title'];
        $lines[]='Organización: '.(string)$election['organization_name'];
        $lines[]='Estado: '.(string)$election['status'];
        $lines[]='Generada: '.date('Y-m-d H:i:s');
        $total=(int)($summary['participation']['total']??0);
        $voted=(int)($summary['participation']['voted']??0);
        $pct=$total>0?round(($voted/$total)*100,2):0;
        $lines[]='Censo: '.$total.' | Votos emitidos: '.$voted.' | Participación: '.$pct.'%';
        $lines[]='';
        foreach($summary['questions'] as $question){
            $lines[]='PREGUNTA: '.(string)$question['text'];
            foreach($question['options'] as $option){
                $lines[]=' - '.(string)$option['label'].': '.(string)$option['votes'].' voto(s), peso '.(string)$option['weighted_votes'];
            }
            $lines[]='';
        }
        $lines[]='SHA-256 resultados: '.$sha256;
        $lines[]='Documento de demostración generado por INAUDIS Vote.';
        $pdf=self::minimalPdf($lines);
        Audit::log('acta.pdf_generated','election',(int)$election['id'],['sha256'=>$sha256]);
        return $pdf;
    }

    private static function minimalPdf(array $lines): string
    {
        $objects=[];
        $content="BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
        foreach($lines as $line){
            foreach(self::wrap((string)$line,90) as $wrapped){
                $text=self::pdfText($wrapped);
                $content.='('.$text.") Tj\nT*\n";
            }
        }
        $content.="ET";
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
        $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[5]='<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream";
        $pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $number=>$object){$offsets[$number]=strlen($pdf);$pdf.=$number." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++){$pdf.=str_pad((string)$offsets[$i],10,'0',STR_PAD_LEFT)." 00000 n \n";}
        $pdf.='trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }

    private static function pdfText(string $text): string
    {
        $converted=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
        if($converted===false)$converted=$text;
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$converted);
    }

    private static function wrap(string $text,int $width): array
    {
        if($text==='')return [''];
        return explode("\n",wordwrap($text,$width,"\n",true));
    }
}
