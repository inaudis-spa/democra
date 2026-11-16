<?php
declare(strict_types=1);
namespace InaudisVote\Services;

final class CensusInsightsService
{
    private const PRIVATE_KEYS=['rut','name','email','phone','vote_weight'];

    public static function forDisplay(int $electionId): array
    {
        $fields=array_values(array_filter(
            CensusTemplateService::election($electionId),
            static fn(array $field):bool=>!empty($field['show_on_display'])&&($field['type']??'')==='text'&&!in_array($field['key']??'',self::PRIVATE_KEYS,true)
        ));
        if(!$fields)return [];
        $members=CensusService::list($electionId);$insights=[];
        foreach(array_slice($fields,0,2) as $field){
            $counts=[];$answered=0;$key=(string)$field['key'];
            foreach($members as $member){
                $value=trim((string)($member[$key]??($member['custom_values'][$key]??'')));
                if($value==='')continue;$value=mb_substr($value,0,60);$counts[$value]=($counts[$value]??0)+1;$answered++;
            }
            if($answered===0)continue;
            arsort($counts,SORT_NUMERIC);$visible=array_slice($counts,0,5,true);$other=array_sum(array_slice($counts,5,null,true));if($other>0)$visible['Otros']=$other;
            $items=[];foreach($visible as $label=>$count)$items[]=['label'=>$label,'count'=>$count,'percentage'=>round($count*100/$answered,1)];
            $insights[]=['key'=>$key,'label'=>(string)$field['label'],'total'=>$answered,'items'=>$items];
        }
        return $insights;
    }
}
