<?php namespace WordTreeClassifier;

//Author Amirrule 

use \StanfordTagger\POSTagger;



class TreeClassifier
{
    protected $KEY      =   "YOUR_GOOGLE_API_HERE!";    // google API untuk translate dari indo ke inggris
    protected $host     =   "YOUR_HOSTNAME_HERE!"; // hostname db anda
    protected $user     =   "YOUR_DB_USERNAME!";  //username db anda
    protected $password =   "YOUR_DB_PASSWORD!"; //password db anda
    protected $db       =   "DATABASE_NAME(DEFAULT:TAG)"; //nama database yg di impor
    protected $postag ;     

    public function __construct()
    {
        $this->postag = new \StanfordTagger\POSTagger();
    }

    //fungsi untuk mengubah kata ke bhs inggris
    public function convert($param)
    {
        $API    =   $this->KEY;
        $TEXT   =   urlencode($param);
        $URL    =   "https://translation.googleapis.com/language/translate/v2?key=".$API."&q=".$TEXT."&source=id&target=en&callback=response";

        $fetch  =   file_get_contents($URL);
        $take1  =   explode("response(", $fetch);
        $take2  =   explode(");", $take1[1]);

        $result =   json_decode($take2[0], true);
        $output =   $result["data"]["translations"][0]["translatedText"];

        return $output;
    }

    //fungsi untuk mendapatkan TAG per katanya ( bisa bahasa inggris / indonesia)
    public function getTag($param)
    {
        $result     =   $this->postag->tag($param);// THIS RESULT WILL RETURN ENGLISH LANGUAGE !!
        //$result     =   $this->postag_id($param); // THIS RESOUT WILL RETURN INDONESIAN LANGUAGE !!
        $token      =   strtok($result," ");
        $starti     =   0;

        $kata       =   array();
        $kata2      =   array();

        while ($token !== false)
        {    
            $kata[$starti] = $token;    
            $token = strtok(" ");
            $starti +=1;
        } 

        for($i=0; $i<count($kata);$i++)
        {    
            $stream = strtok($kata[$i],"_");
            $o=0;
            while ($stream !== false)
            {   
                if($o==0)
                    $kata2[$i]["kata"] = $stream;        
                else
                    $kata2[$i]["tag"]  = $stream;        
                    
                $stream = strtok("_");        
                $o++;
            }    
        }        
        return $kata2;
    }

    //
    public function setRule($param)
    {
        $convert    =   $this->convert($param);// FOR ENGLISH LANGUAGE
        $getTag     =   $this->getTag($convert);        
        $literal    =   sizeof($getTag);
 
        for($i=0; $i<$literal; $i++)
        {
            $before =   $getTag[$i-1]["tag"];
            $after  =   $getTag[$i+1]["tag"];
            $now    =   $getTag[$i]["tag"];

            //FIND SUBJEK

            if($i==0 && $this->tagNoun($now))
            {
                $getTag[$i]["keterangan"]   =   "S"; 
            }
            elseif($getTag[$i]["keterangan"] ==   "S" && !$this->tagVerb($now) && !$this->tagPreposition($now))
            {
                if($this->tagAdverb($now) || $this->tagNegation($now) )
                    $getTag[$i]["keterangan"]   =   "P";
                else
                    if($this->tagDeterminer($now))
                        $getTag[$i]["keterangan"]   =   "P";
            }
            elseif($this->tagPRP($now) && $this->tagNoun($before))
            {
                $getTag[$i]["keterangan"]   =   "S";
            }

            //FIND PREDIKAT

            elseif($i==0 && $this->tagVerb($now))
            {
                $getTag[$i]["keterangan"]   =   "P";
                continue;
            }
            elseif($getTag[$i]["keterangan"]   ==   "S" || $getTag[$i]["keterangan"]   ==   "P" && $this->tagVerb($now))
            {
                $getTag[$i]["keterangan"]   =   "P";

                if($this->tagVerb($now) && $this->tagNoun($after))
                    $getTag[$i]["keterangan"]   =   "O";
                    continue;
            }
            elseif($this->tagNoun($before) && $this->tagVerb($now))
            {
                $getTag[$i]["keterangan"]   =   "P";
                continue;
            }
            elseif($this->tagVerb($after) && $this->tagModal($now))
            {
                $getTag[$i]["keterangan"]   =   "P";
                continue;
            }

            //FIND OBJEK

            elseif(($getTag[$i]["keterangan"]   ==   "P" || $getTag[$i]["keterangan"]   ==   "O") && !$this->tagVerb($now))
            {
                $getTag[$i]["keterangan"]   =   "O";

                if(!$this->tagPreposition($now))
                    $getTag[$i]["keterangan"]   =   "O";
                else
                    $getTag[$i]["keterangan"]   =   "K";
            }

            //FIND KETERANGAN
            elseif($getTag[$i]["keterangan"]   ==   "K" && $this->tagNoun($now) && !$this->tagPreposition($now) || $this->tagPreposition($now) )
            {
                $getTag[$i]["keterangan"]   =   "K";
            }
            else
                $getTag[$i]["keterangan"]   =   "K";
        }

        return $getTag;
    }
  
    //MAIN RULE

    public function tagNegation($tag)
    {
        $Rule   =   "KNF"; // INDO TAG ONLY

        if($tag == $Rule)
            return true;
        else
            return false;
    }
    public function tagPRP($tag)
    {
        $Rule   =   array("PRP","PRP$"); // ENGLISH OR INDO OK
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }
    public function tagNoun($tag)
    {
        //$Rule   =   array("NN","NNS","NNP","PRP");// FOR ENGLISH TAG
        $Rule   =   array("KG","KN","NNP","NH","NB");
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }

    public function tagVerb($tag)
    {
        $Rule   =   array("VB","VBD","VBN","VBP","VBZ"); //FOR ENGLISH TAG
        //$Rule   =   array("KK");
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }

    public function tagConjunction($tag)
    {
        $Rule   =   array("CC","ET"); // ENGLISH OR INDO OK !!
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }

    public function tagNumeral($tag)
    {
        $Rule   =   "CD"; // ENGLISH OR INDO OK !!

        if($tag == $Rule)
            return true;
        else
            return false;
    }

    public function tagDeterminer($tag)
    {
        $Rule   =   "DT"; // ENGLISH OR INDO OK !!

        if($tag == $Rule)
            return true;
        else
            return false;
    }

    public function tagPreposition($tag)
    {
        $Rule   =   array("IN","TO"); // FOR ENGLISH TAG !
        //$Rule   =   array("KPRE");
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }

    public function tagAdjective($tag)
    {
        $Rule   =   array("JJ","JJR","JJS"); //FOR ENGLISH TAG !!
        //$Rule   =   array("ADJ");
        $status =   false;

        for($i = 0; $i < count($Rule); $i++)
        {
            if($tag == $Rule[$i]){
                $status = true; 
                break;        
            }
        }

        if($status)
            return true;
        else
            return false;
    }

    public function tagModal($tag)
    {   
        $Rule   =   "MD"; // ENGLISH OR INDO OK !

        if($tag == $Rule)
            return true;
        else
            return false;
    }

    public function tagAdverb($tag)
    {
       $Rule   =   array("RB","RBR","RBS");   // FOR ENGLISH TAG
        //$Rule   =   "ADV";
        $status =   false;

        
        if($tag == $Rule)
            return  true; 
        else
            return false;
    }


    public function Drawtree($param)
    {
        $material   =   $this->setRule($param);

        $literal    =   sizeof($material);

        $getkalimat =   $param;
        
        $sekrip     =   "";

        $sekripspok =   "var config={container: '#OrganiseChart-simple'};var parent_node={text:{name:'".$param."'}};";
        
        $config     =   "config,parent_node,";
        $spok       =   array(
            "S" =>  false,
            "P" =>  false,
            "O" =>  false,
            "K" =>  false,
            "U" =>  false);
        
        for($i=0;$i<$literal;$i++)
        {
            $keterangan =   $material[$i]["keterangan"];
            $kata       =   $material[$i]["kata"];
            $tag        =   $material[$i]["tag"];
            
            
            if($keterangan =="S")
            {                                   
                
                $spok["S"]  = true;                
                $sekrip .= "var ".$tag."D".$i."={parent:S,text:{name:'".$tag."'}};var ".$kata."D".$i."={parent:".$tag."D".$i.",text:{name:'".$kata."'}};";
                $config .= "S,".$tag."D".$i.",".$kata."D".$i.",";
            }
            elseif($keterangan =="P")    
            {                    
                $spok["P"]  = true;                
                $sekrip .= "var ".$tag."D".$i."={parent:P,text:{name:'".$tag."'}};var ".$kata."D".$i."={parent:".$tag."D".$i.",text:{name:'".$kata."'}};";
                $config .= "P,".$tag."D".$i.",".$kata."D".$i.",";
            }
            elseif($keterangan =="O") 
            {                                
                $spok["O"]  = true;                
                $sekrip .= "var ".$tag."D".$i."={parent:O,text:{name:'".$tag."'}};var ".$kata."D".$i."={parent:".$tag."D".$i.",text:{name:'".$kata."'}};";
                $config .= "O,".$tag."D".$i.",".$kata."D".$i.",";
            }
            elseif($keterangan =="K") 
            {        
                $spok["K"]  = true;                                
                $sekrip .= "var ".$tag."D".$i."={parent:K,text:{name:'".$tag."'}};var ".$kata."D".$i."={parent:".$tag."D".$i.",text:{name:'".$kata."'}};";
                $config .= "K,".$tag."D".$i.",".$kata."D".$i.",";
            }
            else
            {             
                $spok["U"]  = true;                
                $sekrip .= "var ".$tag."D".$i."={parent:U,text:{name:'".$tag."'}};var ".$kata."D".$i."={parent:".$tag."D".$i.",text:{name:'".$kata."'}};";
                $config .= "U,".$tag."D".$i.",".$kata."D".$i.",";
            } 
            
               
        }
        if($spok["S"])
            $sekripspok .= "var S={parent:parent_node,text:{name:'SUBJEK'}};";            
        if($spok["P"])
            $sekripspok .= "var P={parent:parent_node,text:{name:'PREDIKAT'}};";        
        if($spok["O"])
            $sekripspok .= "var O={parent:parent_node,text:{name:'OBJEK'}};";    
        if($spok["K"])
            $sekripspok .= "var K={parent:parent_node,text:{name:'KETERANGAN'}};"; 
        if($spok["U"])
            $sekripspok .= "var U={parent:parent_node,text:{name:'UNDEFINED}};";  
                                
            
        $setting    =   "var simple_chart_config = [".$config."];";
        $draw       =   $sekripspok.$sekrip . $setting;
        return $draw;        
    }


    public function postag_id($param)
    {        
        $words  =   explode(" ",$param);

        for($i=0; $i<sizeof($words); $i++)
        {    
            $nana       =   $this->Stag($words[$i]);
            $swag       =   mysqli_fetch_array($nana);    
            $tmp        =   $words[$i]."_".$swag["postag"];
            $result    .=   $tmp." ";
        }
        
        return $result;

    }
    
    function run($query){
        $link = mysqli_connect($this->host,$this->user,$this->password,$this->db);    
   
       if($result = mysqli_query($link,$query) or die('gagal')){
            return $result;
       }
   }
   
   function Stag($param){
       $query ="SELECT postag FROM lexical where lexical.lexicon ='$param';";
       return $this->run($query);
   }

}

