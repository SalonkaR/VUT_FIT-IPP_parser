<?php
    
/**
 * @file parse.php
 * @author Matus Tvarozny, xtvaro00
 * @brief IPP projekt 1
 */
    
ini_set('display_errors', 'stderr');

$statp = false;
$header = false;
$counter = -1; //-1 because of header

/**
 * funkcia na vratenie a vypis konkretne chyby 
 * @param int $err_code cislo chyby
 */

function error_writer(int $err_code){
    switch($err_code){
        case 21:
            fwrite(STDERR, "21 - chybna alebo chybajuca hlavicka v zdrojovom kode zapisanom v IPPcode21\n");
            exit(21);
        case 22:
            fwrite(STDERR, "22 - neznamy alebo chybny operacny kod v zdrojovom kode zapisanom v IPPcode21\n");
            exit(22);
        case 23:
            fwrite(STDERR, "23 - ina lexikalna alebo syntakticka chyba zdrojoveho kodu zapisaneho v IPPcode21\n");
            exit(23);
    }
}

/** 
 * funkcia vracia indexy na ktorych sa nachadza backslash
 * @param string $haystack string v ktorom sa bude hladat
 * @param string $needle vyhladavany znak
 * @return $allpos zoznam indexov  
 */
function escape(string $haystack, string $needle) {
    $offset = 0;
    $allpos = array();
    while (($pos = strpos($haystack, $needle, $offset)) !== FALSE) {
        $offset   = $pos + 1;
        $allpos[] = $pos;
    }
    return $allpos;
}

/**
 * funkcia kontroluje ci je \<symb> validny, ak ano vypise potrebny riadok XML,
 * ak nie, je volana funkcia error_writer() s korektnym cislom chyby
 * @param string $string konkretny symbol
 * @param int $number poradove cislo argumentu
 */
function symbol(string $string, int $number){
    if(preg_match('/^(LF|GF|TF)@[a-zA-Z\_\-$&\<\>%*!?][a-zA-Z\_\-$&\<\>%*!?0-9]*$/', $string)){
        $string = str_replace("&", "&amp;", $string);
        $string = str_replace("<", "&lt;", $string);
        $string = str_replace(">", "&gt;", $string);
        echo("  <arg".$number." type=\"var\">".$string."</arg".$number.">\n");
    }elseif(preg_match('/^int@[-+]?[0-9]+$/', $string)){
        echo("  <arg".$number." type=\"int\">".substr($string, 4-strlen($string))."</arg".$number.">\n");
    }elseif(preg_match('/^bool@(true|false)$/', $string)){
        echo("  <arg".$number." type=\"bool\">".substr($string, 5-strlen($string))."</arg".$number.">\n");
    }elseif(preg_match('/^string@$/', substr($string, 0, 7))){
        if(strpos(substr($string, 7-strlen($string)), "\\")){
            $allpos = escape($string, "\\");
            for($i = 0; $i < count($allpos); $i++){
                if(!preg_match('/[0-9][0-9][0-9]/', substr($string, $allpos[$i], $allpos[$i]+3))){
                    error_writer(23);
                }
            }
        }
        $string = str_replace("&", "&amp;", $string);
        $string = str_replace("<", "&lt;", $string);
        $string = str_replace(">", "&gt;", $string);
        echo("  <arg".$number." type=\"string\">".substr($string, 7-strlen($string))."</arg".$number.">\n");
    }elseif(preg_match('/^nil@nil$/', $string)){
        echo("  <arg".$number." type=\"nil\">nil</arg".$number.">\n");
    }else{
        error_writer(23);
    }
}

/**
 * funkcia kontroluje ci je \<var> validna premenna, ak ano vypise potrebny riadok XML,
 * ak nie, je volana funkcia error_writer() s korektnym cislom chyby
 * @param string $string konkretna premenna
 * @param int $number poradove cislo argumentu
 */
function variable(string $string, int $number){
    if(preg_match('/^(LF|GF|TF)@[a-zA-Z\_\-$&\<\>%*!?][a-zA-Z\_\-$&\<\>%*!?0-9]*$/', $string)){
        $string = str_replace("&", "&amp;", $string);
        $string = str_replace("<", "&lt;", $string);
        $string = str_replace(">", "&gt;", $string);
        echo("  <arg".$number." type=\"var\">".$string."</arg".$number.">\n");
    }else{
        error_writer(23);
    }
}

/**
 * funkcia kontroluje ci je \<label> validny navestie, ak ano vypise potrebny riadok XML,
 * ak nie, je volana funkcia error_writer() s korektnym cislom chyby
 * @param string $string konkretne navestie
 * @param int $number poradove cislo argumentu
 */
function label(string $string, int $number){
    if(preg_match('/^[a-zA-Z\_\-$&\<\>%*!?][a-zA-Z\_\-$&\<\>%*!?0-9]*$/', $string)){
        $string = str_replace("&", "&amp;", $string);
        $string = str_replace("<", "&lt;", $string);
        $string = str_replace(">", "&gt;", $string);
        echo("  <arg".$number." type=\"label\">".$string."</arg".$number.">\n");
    }else{
        error_writer(23);
    } 
}

/**
 * funkcia kontroluje ci je \<type> validny typ, ak ano vypise potrebny riadok XML,
 * ak nie, je volana funkcia error_writer() s korektnym cislom chyby
 * @param string $string konkretny typ
 * @param int $number poradove cislo argumentu
 */
function type(string $string, int $number){
    if(preg_match('/^(int|string|bool)$/', strtolower($string))){
        echo("  <arg".$number." type=\"type\">".$string."</arg".$number.">\n"); 
    }else{
        error_writer(23);
    }
}

//premenne patriace pod rozsirenie STATP
$statp_loc = 0;
$statp_comments = 0;
$statp_labels = 0;
$statp_jumps = 0;
$statp_fwjumps = 0;
$statp_backjumps = 0;
$statp_badjumps = 0;
$statp_labels_list = [];

//kontrola argumentov prikazoveho riadku
if($argc > 1){
    if(array_search("--help", $argv)){
        echo("pouzitie: php parse.php <[input_file] [--stats=file [--loc, --comments, --labels, --jumps, --fwjumps, --backjumps, --badjumps]]\n");
        exit(0);
    }elseif(preg_match("/--stats=.+/", $argv[1])){
        $statp = true;
        $stats_outputs = [];
        for($i = 0; $i < $argc; $i++){
            if(preg_match("/--stats=.+/", $argv[$i])){
                if(in_array(substr($argv[$i], 8), $stats_outputs)){
                    exit(12);
                }
                array_push($stats_outputs, substr($argv[$i], 8));
            }
        }
    }else{
        fwrite(STDERR, "10 - chybajuci parameter skriptu alebo pouzitie zakazanej kombinacie parametrov\n");
        exit(10);
    }
}

//dopredne ulozenie vsetkych obsiahnutych navesti kvoli rozsireniu STATP
$statp_all_labels_list = [];
while($line = fgets(STDIN)){
    $splitted = explode(' ', trim($line, "\n"));
    if($splitted[0] == "LABEL"){
        if(count($splitted) == 2){
            array_push($statp_all_labels_list, $splitted[1]);
        }else{
            error_writer(23);
        }
    }
}

//resetnutia pointera pre opatovne citanie z STDIN
rewind(STDIN);

//parser
while($line = fgets(STDIN)){
    //odstranenie komentarov
    for($i = 0; $i < strlen($line); $i++){
        if($line[$i] == "#"){
            $statp_comments++;
            $line = substr_replace($line, "", $i, strlen($line)-$i);
            break;
        }   
    }
    //preskakovanie riadkov ktore obsahuju len komentare 
    if(strlen($line) == 0){
        continue;
    }

    $line = trim($line);

    //preskakovanie prazdnych riadkov
    if(empty($line)){
        continue;
    }

    $counter += 1;

    //kontrola ci je obsiahnuta (spravna) hlavicka
    if($header == false){   //&& counter == 1(musi byt na vrchu)
        if(strtoupper($line) == ".IPPCODE21"){
            $header = true;
            echo("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            echo("<program language=\"IPPcode21\">\n");
            continue;
        }
        else{
            error_writer(21);
        }
    }

    $splitted = explode(' ', trim($line, "\n"));
    $instruction = strtoupper($splitted[0]);

    echo(" <instruction order=\"".$counter."\" opcode=\"".$instruction."\">\n");
    
    //implementacia parseru pomocou switchu
    switch($instruction){
        case 'MOVE':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'CREATEFRAME':
            if(count($splitted) != 1){
                error_writer(23);
            }
            //STATP
            $statp_loc++;
            break;
        case 'PUSHFRAME':
            if(count($splitted) != 1){
                error_writer(23);
            }
            //STATP
            $statp_loc++;
            break;
        case 'POPFRAME':
            if(count($splitted) != 1){
                error_writer(23);
            }
            //STATP
            $statp_loc++;
            break;
        case 'DEFVAR':
            if(count($splitted) != 2){
                error_writer(23);
            }
            variable($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;
        case 'CALL':
            if(count($splitted) != 2){
                error_writer(23);
            }
            label($splitted[1], 1);
            //STATP
            if(in_array($splitted[1], $statp_labels_list) && in_array($splitted[1], $statp_labels_list)){
                $statp_backjumps++;
            }elseif(in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_fwjumps++;
            }elseif(!in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_badjumps++;
            }
            $statp_jumps++;
            $statp_loc++;
            break;
        case 'RETURN':
            if(count($splitted) != 1){
                error_writer(23);
            }
            //STATP
            $statp_jumps++;
            $statp_loc++;
            break;
        case 'PUSHS':
            if(count($splitted) != 2){
                error_writer(23);
            }
            symbol($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;
        case 'POPS':
            if(count($splitted) != 2){
                error_writer(23);
            }
            variable($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;
        case 'ADD':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'SUB':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'MUL':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'IDIV':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'LT':
        case 'GT':
        case 'EQ':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'AND':
        case 'OR':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'NOT':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'INT2CHAR':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'STRI2INT':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;          
        case 'READ':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            type($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'WRITE':
            if(count($splitted) != 2){
                error_writer(23);
            }
            symbol($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;  
        case 'CONCAT':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'STRLEN':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'GETCHAR':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'SETCHAR':
            if(count($splitted) != 4){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            $statp_loc++;
            break;
        case 'TYPE':
            if(count($splitted) != 3){
                error_writer(23);
            }
            variable($splitted[1], 1);
            symbol($splitted[2], 2);
            //STATP
            $statp_loc++;
            break;
        case 'LABEL':
            if(count($splitted) != 2){
                error_writer(23);
            }
            label($splitted[1], 1);
            //STATP
            array_push($statp_labels_list, $splitted[1]);
            $statp_labels++;
            $statp_loc++;
            break;
        case 'JUMP':
            if(count($splitted) != 2){
                error_writer(23);
            }
            label($splitted[1], 1);
            //STATP
            if(in_array($splitted[1], $statp_labels_list) && in_array($splitted[1], $statp_labels_list)){
                $statp_backjumps++;
            }elseif(in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_fwjumps++;
            }elseif(!in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_badjumps++;
            }
            $statp_jumps++;
            $statp_loc++;
            break;
        case 'JUMPIFEQ':
            if(count($splitted) != 4){
                error_writer(23);
            }
            label($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            if(in_array($splitted[1], $statp_labels_list) && in_array($splitted[1], $statp_labels_list)){
                $statp_backjumps++;
            }elseif(in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_fwjumps++;
            }elseif(!in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_badjumps++;
            }
            $statp_jumps++;
            $statp_loc++;
            break;
        case 'JUMPIFNEQ':
            if(count($splitted) != 4){
                error_writer(23);
            }
            label($splitted[1], 1);
            symbol($splitted[2], 2);
            symbol($splitted[3], 3);
            //STATP
            if(in_array($splitted[1], $statp_labels_list) && in_array($splitted[1], $statp_labels_list)){
                $statp_backjumps++;
            }elseif(in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_fwjumps++;
            }elseif(!in_array($splitted[1], $statp_all_labels_list) && !in_array($splitted[1], $statp_labels_list)){
                $statp_badjumps++;
            }
            $statp_jumps++;
            $statp_loc++;
            break;
        case 'EXIT':
            if(count($splitted) != 2){
                error_writer(23);
            }
            symbol($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;
        case 'DPRINT':
            if(count($splitted) != 2){
                error_writer(23);
            }
            symbol($splitted[1], 1);
            //STATP
            $statp_loc++;
            break;
        case 'BREAK':
            if(count($splitted) != 1){
                error_writer(23);
            }
            //STATP
            $statp_loc++;
            break;
        default:
            error_writer(22);
    }
    echo(" </instruction>\n");
}
echo("</program>\n");


//ukladanie potrebnych hodnot do suborov - rozsirenie STATP 
if($statp){
    for($i = 1; $i < $argc; $i++){
        if(preg_match("/--stats=.+/", $argv[$i])){
            $tempfile = fopen(substr($argv[$i], 8), "w");
            continue;
        }
        switch($argv[$i]){
            case "--loc":
                fwrite($tempfile, $statp_loc."\n");
                break;
            case "--comments":
                fwrite($tempfile, $statp_comments."\n");
                break;
            case "--labels":
                fwrite($tempfile, $statp_labels."\n");
                break;
            case "--jumps":
                fwrite($tempfile, $statp_jumps."\n");
                break;
            case "--fwjumps":
                fwrite($tempfile, $statp_fwjumps."\n");
                break;
            case "--backjumps":
                fwrite($tempfile, $statp_backjumps."\n");
                break;
            case "--badjumps":
                fwrite($tempfile, $statp_badjumps."\n");
                break;
            default:
                fwrite(STDERR, "10 - chybajuci parameter skriptu alebo pouzitie zakazanej kombinacie parametrov\n");
                exit(10);
        }
    }
}

//pokial hlavicka (spravna) nebola obsiahnuta
if($header == false){
    error_writer(21);
}

exit(0);
?>