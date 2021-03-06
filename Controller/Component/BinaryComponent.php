<?php

/**
 * Component to copy and create Binary Files with new translates
 *
 */
set_time_limit(0);
class BinaryComponent extends Component {
		
	/**
	 * Initialize vars
	 * @param ComponentCollection $collection
	 * @param array $settings
	 */
	function __construct(ComponentCollection $collection, $settings = array())
	{	
		$this->pos_ini = 14336; // 0x3800
		
		$this->separator = pack("H*" , "ff");
		$this->new_line = pack("H*" , "f2");
		$this->new_page = pack("H*" , "f6");

		/** 
		 * WARNING!!! NO CHANGE THE ORDER from special_chars after insert the binary texts
		 * */ 
		$this->wildcard_chars_orig = array(
				$this->new_line, // <0>
				pack("H*" , "ec"), $this->new_page, // <1>=Button A, <2>
				pack("H*" , "ee"), pack("H*" , "f0"), // <3>=Button B, <4>=Button C
				pack("H*" , "fe"), pack("H*" , "f4"), pack("H*" , "fa"), pack("H*", "fb"), pack("H*", "fc"), // <5>, <6>, <7>, <8>, <9>
				pack("H*" , "fd"), pack("H*" , "f9"), // <10>, <11>
				pack("H*" , "f7"), pack("H*" , "f8")  // <12>, <13>
		);
				
		$this->wildcard_chars_temp = array();
		foreach($this->wildcard_chars_orig as $key=>$value){
			$this->wildcard_chars_temp[] = "<".$key.">";
		}
		
		// Replace characters that are not supported by original BINCODE file for a modified BINCODE file
		$this->array_old_lang_chars = array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', '¡', '¿', 'ü', 'Ü', 'º', 'ª');
		$this->array_new_lang_chars = array('`', '{', '|', '}', '~', 'A', 'E', 'I', 'O', 'U', '^', '=', '*', '\\', '$', 'U', pack("H*", "80"), pack("H*", "85"));
	
		$this->long_text = 224;  		// Min pixels text in lines 1, 2, 3 and 4
		$this->long_text_min = 208; 	// Min pixels text in lines 5 and 6
		$this->long_menu = 224; 		// Min pixels text in menu
		$this->long_jordan = 160; 		// Min pixels text in Jordan
		
		// Number of pixels for each char
		$this->long_chars = array(
				' ' => 6, '!' => 7, '"' => 7, '#' => 8, '$' => 7, '%' => 9, '&' => 8, '\''=> 4, 
				'(' => 7, ')' => 6, '*' => 7, '+' => 7, ',' => 4, '-' => 7, '.' => 3, '/' => 9,  
				'0' => 8, '1' => 6, '2' => 8, '3' => 8, '4' => 8, '5' => 8, '6' => 8, '7' => 8, 
				'8' => 8, '9' => 8, ':' => 4, ';' => 4, '<' => 9, '=' => 7, '>' => 9, '?' => 7,
				'@' => 8, 'A' => 9, 'B' => 8, 'C' => 8, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 8, 
				'H' => 9, 'I' => 5, 'J' => 7, 'K' => 9, 'L' => 8, 'M' => 9, 'N' => 8, 'N' => 8, 'O' => 8,
				'P' => 8, 'Q' => 8, 'R' => 9, 'S' => 8, 'T' => 8, 'U' => 9, 'V' => 8, 'W' => 9, 
				'X' => 8, 'Y' => 8, 'Z' => 8, '[' => 6, '\\'=> 7, ']' => 7, '^' => 7, '_' => 8,
				'`' => 7, 'a' => 7, 'b' => 6, 'c' => 6, 'd' => 7, 'e' => 6, 'f' => 7, 'g' => 7, 
				'h' => 6, 'i' => 4, 'j' => 6, 'k' => 6, 'l' => 4, 'm' => 8, 'n' => 7, 'o' => 6,
				'p' => 6, 'q' => 7, 'r' => 7, 's' => 6, 't' => 5, 'u' => 7, 'v' => 6, 'w' => 8,
				'x' => 7, 'y' => 6, 'z' => 6, '{' => 7, '|' => 4, '}' => 7, '~' => 7, '©' => 9,
				pack("H*" , "ec") => 9, pack("H*" , "ee") => 8, pack("H*" , "f0") => 8, // <1>, <3>, <4>
		);

		// Special characters, values in hex
		$this->character_computer = Configure::read('Snatcher.Characters.computer');
		$this->character_menu = Configure::read('Snatcher.Characters.menu');		
		$this->character_nobody = Configure::read('Snatcher.Characters.nobody');
	}	
	
	/**
	 * Strpos recursive
	 * @param $haystack The string to search in
	 * @param $needle Character to search
	 * @return array with positions
	 */
	private function strpos_recursive($haystack, $needle)
	{
		$results = array();
		$offset = 0;
		
	    $offset = strpos($haystack, $needle, $offset);
	    if($offset === false) {
	        return $results;
	    } else {
	        $results[] = $offset;
	        while($offset = strpos($haystack, $needle, ($offset+1))){
	        	$results[] = $offset;
	        }
	    }
	    
	    return $results;
	}
	
	/**
	 * Create temporal file $filename with chars in $str
	 * @param string $filename
	 * @param string $str
	 */
	private function _createFile($filename, $str){
		$fp = fopen($filename, 'w') or die("can't open file"); 
		
		fwrite($fp, $str);	
		
		fclose($fp);
	}
	
	/**
	 * Get number of pixels from a char $str
	 */
	private function str_pixel_len($str){
		$long = 0;
		
		for($i=0; $i < strlen($str); $i++)
		{
			if( isset($this->long_chars[substr($str, $i, 1)]) )
			{
				$long += $this->long_chars[substr($str, $i, 1)];
			}
		}

		return $long;
	}
	
	/**
	 * Check limits in pixels number os line $nlines.
	 * If character_id value is 2 it is a Jordan line.
	 * @param int $nlines Number of lines, start in 0. 
	 * @param booelan $iscomputer if true it is a Jordan line.
	 * @return int limite en pixels Number of limit in pixels
	 */
	private function checkLimitPixels($nlines, $iscomputer)
	{
		if($iscomputer){
			return $this->long_jordan;
		}
		
		if( ($nlines%6==4) || ($nlines%6==5) ){
			return $this->long_text_min;
		}
		
		return $this->long_text;
	}
	
	/**
	 * Split line at maximum pixels per line
	 * @param String $str complete text
	 * @param int $nlines
	 * @param boolean $iscomputer
	 * @return String splitted text
	 */
	private function splitLine($str, &$nlines = 0, $iscomputer = null)
	{		
		// if pixels < limit return an unique line
		$limit = $this->checkLimitPixels($nlines, $iscomputer);
		if( $this->str_pixel_len($str) <= $limit )
		{
			return $str;
		}

		// return cadena if unique word
		$array = explode(" ", $str);
		if(count($array) < 2)
		{
			return $str;
		}
		
		$text = "";
		foreach($array as $word){
			$limit = $this->checkLimitPixels($nlines, $iscomputer);

			if( $this->str_pixel_len($text.$word) > $limit )
			{
				$line[] = trim($text);
				$text = "";
				$nlines++;
			}

			$text .= $word." ";
		}
		$line[] = trim($text);
		return implode($this->new_line, $line);
	}
	
	/**
	 * Explode like original function but for n chars as delimits
	 * using strtok function.
	 * @param string $str to explode
	 * @return multitype:string
	 */
	private function multiple_explode($str){
		$array = array();
		$delim = "\r\n".$this->new_line;
		
		$tok = strtok($str, $delim);
		while ($tok !== false) {
			$array[] = $tok;
			$tok = strtok($delim);
		}
		
		return $array;
	}

	/**
	 * Split a text with $this->new_line or \r or \n
	 * que superar un límite dado por $this->long_text
	 * @param String $str
	 * @param boolean $iscomputer
	 * @return string
	 */
	private function splitText($str, $iscomputer)
	{	
		$array = $this->multiple_explode($str);
		
		$lines = array();
		$nlines = 0;
		foreach ($array as $line)
		{
			$lines[] = $this->splitLine($line, $nlines, $iscomputer);
			$nlines++;
		}

		return implode($this->new_line, $lines);
	}
	
	/**
	 * Explode cadena with $new_page special character if exists.
	 * If some sentence uses $new_page it will create a new page in game.
	 * If not exists $new_page in sentence, return $str splitted by $new_line
	 * @param string $str
	 * @param int $count number of actual line
	 * @param int $character_id
	 * @return string
	 */
	private function splitPage($str, $iscomputer){
	
		// Si No hay caracter especial salto de página (<2>)
		if(strpos($str, $this->new_page) === FALSE){
			return $this->splitText($str, $iscomputer);
		}
	
		$pages = array();
		$array = explode($this->new_page, $str);
		foreach($array as $page){
			$pages[] = $this->splitText($page, $iscomputer);
		}

		return implode($this->new_page, $pages);
	}	
	
	/**
	 * Debug mode to check if all data is correct.
	 * You'll need $str as string with first bytes until 0x3800 from binary file 
	 * @param int $offset
	 * @param int $character
	 * @param string $str
	 * @return NULL|mixed
	 */
	private function _debugCheckOffset($offset, $character, $str)
	{
		$str_hex = bin2hex($str);
		
		$multiple = false;
		
		switch($character)
		{
			case $this->character_menu:
				$charlen = 2;
				$search  = $this->character_menu . $offset;
				$multiple = true;
				break;
		
			case $this->character_nobody:
				$charlen = 2;
				$search  = $this->character_nobody . $offset;
				break;
		
			default:
				$charlen = 8;	
				$search  = "432000" . $character . $offset;
		}
		
		$count = 0;
		
		// Search offset in $str
		$array_pos = $this->strpos_recursive($str_hex, $offset);
						
		$all_offsets = array();
		
		foreach($array_pos as $pos)
		{
			if($pos%2 == 0)
			{	
				$tmp_offset = substr($str_hex, $pos-$charlen, strlen($offset)+$charlen);
				
				$tmp_char = substr($str_hex, $pos-8, 6);
				$tmp_char2 = substr($str_hex, $pos-2, 2);
			
				if( ($character != $this->character_menu) &&
					($tmp_offset != $search) &&
					(
						($tmp_char == "432000") ||	
						($tmp_char2 == $this->character_nobody)								
					)
				){
					debug($tmp_offset);
				}
				
				if($tmp_offset == $search)
				{
					$count++;				
					break;
				}
				
				$all_offset[] = $tmp_offset;
				
				if(!$multiple && $count>1)
				{
					debug($offsets);
					die("Multiple offsets found for single text offset.");
				}
			}
		}
		
		if($count == 0)
		{
			debug("No found $offset: $character");
			debug($all_offsets);
			die;
		}
		
				
	}
	
	/**
	 * Get preoffset necessary to replace old offset
	 * from a specific text.
	 * Fix "38 00 25" offset to replace "F9 38 00 25"
	 * @param string $filename
	 * @param stirng $offset
	 */
	private function _get_pre_offset($filename, $text_offset)
	{
		$result = "";
	
		switch($filename){
			case 'SP24.BIN':
				if($text_offset == 37)
					$result = "f9";
					break;
			default:
		}
	
		return $result;
	}
	
	/**
	 * A partir de un array de Sentence obtiene un array con los textos.
	 * @param multitype:int $data
	 * @param int $array_offsets
	 * @return mixed
	 */
	private function getNewTexts($data, $str, &$array_offsets = array())
	{
		$sum_offset = 0;
		$pos = 0;
	
		foreach($data as $value)
		{			
			// Debug mode checks if are correct offsets and characters in database 
// 			$this->_debugCheckOffset(					 
// 					sprintf("%04x", $value['text_offset']),
// 					$value['OldCharacter']['hex'],
// 					$str_first
// 					);
			
			// Store new offsets if it changes
			if( 
				($sum_offset != 0) || // Last position equals new position
				($value['OldCharacter']['hex'] != $value['Character']['hex']) // New Character from text  
			){
				$array_new_offsets = array(
						'pre_offset' => $this->_get_pre_offset($value['BinaryFile']['filename'], $value['text_offset']),
						'old_offset' => sprintf("%04x", $value['text_offset'] ),
						'new_offset' => sprintf("%04x", $value['text_offset'] + $sum_offset),
						'old_character' => $value['OldCharacter']['hex'],
						'new_character' => $value['Character']['hex']
				);
				$array_offsets[] = $array_new_offsets;
			}

			/* replace mapped characters like accent vowels */
			$str = str_replace($this->array_old_lang_chars, $this->array_new_lang_chars, $value['new_text']);
			
			/* replace special characters in "<XX>" format */
			$str = str_replace($this->wildcard_chars_temp, $this->wildcard_chars_orig, $str);
			
			// If it is not a menu text, split in pages(<2>) and lines(<0>) 
			if( $value['OldCharacter']['hex'] != $this->character_menu)
			{
				$isComputer = $value['OldCharacter']['hex'] == $this->character_computer;
				$str = $this->splitPage($str, $isComputer); 
			}
			
			$sum_offset += strlen($str) - $value['nchars']; // Necesario para calcular el nuevo offset de siguientes textos.
	
			$array[] = $str;
		}
		
		return implode($this->separator, $array);
	}
	
	/**
	 * Search and replace offset texts
	 * @param array $offsets
	 * @param string $result
	 * @param string $search offset to search
	 * @param string $replace offset to replace
	 * @param boolean $multiple if true can replace more than one offset
	 * @return string|NULL
	 */
	private function str_replace_offset_texts($offsets, $result, $search, $replace, $multiple = false){
		
		$count = 0;
		
		// Search all positions
		$array_pos = $this->strpos_recursive($result, $search);	
		
		foreach($array_pos as $pos)
		{
			// Only replace if its a correct position from $search in $subject
			if($pos%2 == 0)
			{
				$result = substr_replace($result, $replace, $pos, strlen($replace));				
				$count++;
				
				if(!$multiple && $count>1){
					debug($offsets);
					die("Multiple offsets found for single text offset.");
				}
			}
		}
		
		if($count == 0)
		{
			return null;
		}		
		
		return $result;
	}
	
	/**
	 * Replaces the previous offset by new offset from the text with the next values​​:
	 * 		if it is a menu: 38{offset} 
	 * 		if it is a text: 432000XX{offset} where XX has between 02 and 30 (base 16) values
	 *	  	if it is a nobody text: 44{offset}
	 * @param array $offset new and original offsets and character from the translated text. 
	 * @param string $subject hex binary code where replace offsets.
	 */
	private function str_replace_offset($offsets, $subject)
	{	
		$multiple = false;				
		
		switch($offsets['old_character'])
		{
			case $this->character_menu:
				$search  = $offsets['pre_offset'] . $this->character_menu . $offsets['old_offset'];
+				$replace = $offsets['pre_offset'] . $this->character_menu . $offsets['new_offset'];
				$multiple = true;
				break;
				
			case $this->character_nobody:
				$search  = $this->character_nobody . $offsets['old_offset'];
				$replace = $this->character_nobody . $offsets['new_offset'];
				break;
				
			default:
				// Search characters texts before 0x432000
				$search  = "432000" . $offsets['old_character'] . $offsets['old_offset'];
				$replace = "432000" . $offsets['new_character'] . $offsets['new_offset'];					
		}	
		
		return $this->str_replace_offset_texts($offsets, $subject, $search, $replace, $multiple);
	}
	
	/**
	 * Recorre un array y sustituye los offsets
	 * @param array $array
	 * @param string $str
	 */
	private function array_replace_offsets($array, $str)
	{
		$str_hex = bin2hex($str);
		
		// Empezar a sustituir
		$black_list = array();
		foreach($array as $offsets)
		{
			if(in_array($offsets['old_offset'], $black_list, true))
			{
				debug($offsets['old_offset']);
				die("Error: This offset has already been replaced before.");
			}
				
			$str_hex = $this->str_replace_offset($offsets, $str_hex);
			if( $str_hex == NULL)
			{
				debug($offsets);
				die("Error: Valid old offset " . base_convert($offsets['old_offset'], 16, 10) . " (base 10) not found in file");
			}
			
			$black_list[] = $offsets['new_offset']; // add to black list
		}
		
		return 	pack("H*" , $str_hex);
	}
	
	/**
	 * Ordena para que no coincidan los offsets
	 * @param $array Array con los datos
	 * @param $array_first Se inicializa a vacio
	 * @param $array_last  Se inicializa a vacio
	 */
	private function orderArrayFisrtLastOffsets($array = array(), &$array_first = array(), &$array_last = array())
	{
		$black_list = $array_first = $array_last = array();
		
		foreach ($array as $offsets)
		{
			if( in_array($offsets['old_offset'], $black_list, true) )
			{
				$array_first[] = $offsets;
			}else $array_last[] = $offsets;
			$black_list[] = $offsets['new_offset']; // add to black list
		}
	}
	
	/**
	 * If new offsets that could overwrite other previous offsets 
	 * rearranges the array of offsets to be changed first before.
	 * @param array() $array 
	 * @return $array
	 */
	private function orderArrayOffsets($array = array())
	{		
		$this->orderArrayFisrtLastOffsets($array, $array_first, $array_last);
		
		// Obtener primeros offsets que se deben cambiar
		while( !empty($array_first)  )
		{
			$this->orderArrayFisrtLastOffsets($array_first, $array_aux_first, $array_aux_last);
			$array_first = $array_aux_first;
			
			$order[] = array_merge($array_aux_first, $array_aux_last);
		}

		// Reordenar y meter del revés en array_first, los últimos offsets son los que deben de ir primero.
		if(isset($order))
		{
			$array_first = array();
			$order = array_reverse($order);
			foreach ($order as $v)
			{
				foreach ($v as $v2)
				{
					if( !in_array ( $v2 , $array_first, TRUE ) ){
						$array_first[] = $v2;
					}
				}	
			}
			$array = array_merge($array_first, $array_last);
		}

		return $array;
	}

	/**
	 * Create a file with the new texts and their offsets displaced from the original file. 
	 * @param $filename original file
	 * @param $newfile Path from new file to be created
	 * @param $data texts data
	 * @return NULL|int total filesize from new file. Return null if there is an error.
	 */
	public function writeFile($filename, $newfile, $data)
	{		
		// Get content
		$str = file_get_contents($filename);
		
		// Get first code without texts
		$str_first = substr($str, 0, $this->pos_ini);			
		
		// Get file texts and an array_offsets with offsets from each text
		$str_texts = $this->getNewTexts($data, $str, $array_offsets);
		
		// Order array offsets
		$array_offsets = $this->orderArrayOffsets($array_offsets);
		
		// Replace offsets from texts
		$str_first = $this->array_replace_offsets($array_offsets, $str_first);	
		
		// Merge output with new first code and new texts
		$output = $str_first . $str_texts . $this->separator;
				
		// if output is oder adds 00
		if( strlen($output) & 0 )
		{
			$output .= pack("H*" , "00"); 
		}
		
		// Create new file
		$this->_createFile($newfile, $output);
		
		return filesize($newfile);
	}
	
}
?>
