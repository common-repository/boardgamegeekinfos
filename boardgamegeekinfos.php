<?php
/**
 * @package boardgamegeekinfos
 * @version 1.0.0
 */
/*
Plugin Name: Board Game Geek Infos 
Plugin URI: http://wordpress.org/extend/plugins/boardgamegeekinfos/
Description: Short code for embedding game infos from Board Game Geek
Author: Giorgio Aquino
Version: 1.0.0
Author URI: http://g100g.net/
*/

define("BGG_BASEURL", "http://boardgamegeek.com/");

//define("BGG_API_BOARDGAME", "xmlapi/boardgame/");
define("BGG_API_BOARDGAME", "xmlapi2/thing/");

define("BGG_BOARDGAME", "boardgame/");
define("BGG_DESIGNER", "boardgamedesigner/");
define("BGG_ARTIST", "boardgameartist/");
define("BGG_PUBLISHER", "boardgamepublisher/");
define("BGG_EXPANSION", "boardgameexpansion/");

define("BGG_CATEGORY", "boardgamecategory/");
define("BGG_FAMILY", "boardgamefamily/");
define("BGG_MECHANIC", "boardgamemechanic/");

function bgg_shortcode($atts) {
	
	//Converto il codice del gioco inserito in una tabella con le info
	
	$html_code = "";
	
	extract(shortcode_atts(array(
	      'id' => null
     ), $atts));
	
	if ($id != null) {
	
		if ( !class_exists( 'WP_Http' ) ) {
		
			include_once( ABSPATH . WPINC. '/class-http.php' );
		
		}
		
		$request = new WP_Http;
		$url = BGG_BASEURL.BGG_API_BOARDGAME."?id=".$id;
		$result = $request->request( $url );
		
		$xml_data = $result['body'];
		
		//$xml_data = file_get_contents(BGG_BASEURL.BGG_API_BOARDGAME."?id=".$id);
		
		if ($xml_data != "") {
				
			$xml = new SimpleXMLElement($xml_data);
		
			//Converto l'XML nella tabella con le informazioni	

			//Get Designer from XML
			
			$names = array();
			
			foreach($xml->item->name as $name) {
			
				$type = (string) $name["type"];
				
				if (!empty($type)) {
			
					$item = (object) array(
										"sortindex" => (int) $name["id"],
										"value" => (string) $name["value"]
										);	
								
					if (array_key_exists( $type, $names)) {
						$names[$type][] = $item;
					} else {
						$names[$type] = array($item);
					}	
					
				}	 
				
			}
			
			//Creo l'array degli elementi
			
			$items = array();
			
			foreach($xml->item->link  as $link) {
			
				$type = (string) $link["type"];
			
				if (!empty($type)) {
			
					$item = (object) array(
										"id" => $link["id"],
										"value" => $link["value"]
										);	
								
					if (array_key_exists( $type, $items)) {
						$items[$type][] = $item;
					} else {
						$items[$type] = array($item);
					}	
						
				}	 
				
			}
			
			//Creo l'array dei pool
			
			$polls = array();
			
			foreach($xml->item->poll as $poll) {
			
				$name = (string) $poll["name"];
			
				if (!empty($name)) {
			
					if (array_key_exists( $name, $items)) {
						$polls[$name][] = $poll;
					} else {
						$polls[$name] = array($poll);
					}	
						
				}	 
				
			}
	
	
		
	
		$html_code .= <<<EOT

		<table class="bggapi">
			<tbody>
EOT;
	
		$row = '<tr><td><td scope="row">%s</td><td scope="row">%s</td></tr>';
	
	 if (array_key_exists("boardgamedesigner", $items)) :

		$row_title = __('Designer', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgamedesigner"] as $item) {
				
				$url = BGG_BASEURL.BGG_DESIGNER.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("boardgameartist", $items)) :

		$row_title = __('Artist', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgameartist"] as $item) {
				
				$url = BGG_BASEURL.BGG_ARTIST.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;				
				
	if (array_key_exists("boardgamepublisher", $items)) :

		$row_title = __('Publisher', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgamepublisher"] as $item) {
				
				$url = BGG_BASEURL.BGG_PUBLISHER.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	//Year
	if ($xml->item->yearpublished) {
	$row_title = __('Year Published', 'boardgamegeek');
	$row_content =  (string) $xml->item->yearpublished["value"];
	$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	}
	
	//# of Players
	$row_title = __('# of Players', 'boardgamegeek');	
	$row_content = "";
	
	if ($xml->item->minplayers) $row_content .= (string) $xml->item->minplayers["value"];
	if ($xml->item->maxplayers) $row_content .= " - " . (string) $xml->item->maxplayers["value"];
	
	$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	
	if (array_key_exists("suggested_numplayers", $polls)) :
		
		$row_title = __('User Suggested # of Players', 'boardgamegeek');	
		$row_content = "";

		//Estrapolo i dati dal Poll
		$suggested_numplayers = $polls["suggested_numplayers"];

		$best_player = 0;
		$recommended_player = "";
		$voters = (string) $suggested_numplayers[0]["totalvotes"];
		
		if ($voters > 0) {
		
		//Conto i risultati ed estrapolo il numero maggiore
		
		//$last_best = 0;
		
		foreach ($suggested_numplayers[0]->results as $result) {

			//Best
			if ($result->result[0]["numvotes"] > 0) {
				$best_player = $result[0]["numplayers"];
			}
			
			//Recommended
			if ((int) $result->result[1]["numvotes"] > (int) $result->result[2]["numvotes"]) {
				$recommended_player .= ($recommended_player != "" ? ", " : "") . $result[0]["numplayers"];
			}
			
		}
					
		$row_content .= <<<EOT
					Best with $best_player players<br />
Recommended with $recommended_player players<br />
($voters voters)
EOT;
 
	 	$html_code .= sprintf($row, 
		 						$row_title,
		 						$row_content
								);
		}
 	endif; 
 	
	
	//Playing Time
	$row_title = __('Playing Time', 'boardgamegeek');	
	$row_content = "";
	
	if ($xml->item->playingtime) $row_content .=  (string) $xml->item->playingtime["value"];
	
	$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	
	//Playing Time
	$row_title = __('Mfg Suggested Ages', 'boardgamegeek');	
	$row_content = "";
	
	if ($xml->item->minage) $row_content .= (string) $xml->item->minage["value"] . " and up";
	$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	
	if (array_key_exists("suggested_numplayers", $polls)) {
					
		$row_title = __('User Suggested Ages', 'boardgamegeek');	
		$row_content = "";
		
		//Estrapolo l'età consigliata dagli utenti
					
		$suggested_playerage = $polls["suggested_playerage"];

		$age = 0;
		$best_vote = 0;
		$voters = (string) $suggested_playerage[0]["totalvotes"];
		
		//Conto i risultati ed estrapolo il numero maggiore
		if ($voters > 0) {			
			
			foreach ($suggested_playerage[0]->results->result as $result) {
		
				//Best
				if ((int) $result[0]["numvotes"] > $best_vote) {
					$best_vote = (int) $result[0]["numvotes"];	
					$age = (int)  $result[0]["value"];							
				}						
				
			}
			
			$row_content .= "$age and up<br />($voters voters)";
	
			$html_code .= sprintf($row,
		 						$row_title,
		 						$row_content
								);
							
		}
	}
	
	if (array_key_exists("language_dependence", $polls)) {
		$row_title = __('Language Dependence', 'boardgamegeek');	
		$row_content = "";
		
		//Language dependence
		$language_dependence = $polls["language_dependence"];

		$result_language = "";
		$best_vote = 0;
		$voters = (string) $language_dependence[0]["totalvotes"];
		
		//Conto i risultati ed estrapolo il numero maggiore
		
		//$last_best = 0;
		if ($voters > 0) {
			
			foreach ($language_dependence[0]->results->result as $result) {
		
				//Best
				if ((int) $result[0]["numvotes"] > $best_vote) {
					$best_vote = (int) $result[0]["numvotes"];	
					$result_language = (string) $result[0]["value"];
					
				}						
				
			}
			
			$row_content .= "$result_language<br />($voters voters)";
			
			$html_code .= sprintf($row,
		 						$row_title,
		 						$row_content
								);
		}
	}
	
	if (array_key_exists("boardgamecategory", $items)) :

		$row_title = __('Category', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgamecategory"] as $item) {
				
				$url = BGG_BASEURL.BGG_CATEGORY.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("boardgamemechanic", $items)) :

		$row_title = __('Mechanic', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgamemechanic"] as $item) {
				
				$url = BGG_BASEURL.BGG_MECHANIC.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("boardgameexpansion", $items)) :

		$row_title = __('Expansion', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgameexpansion"] as $item) {
				
				$url = BGG_BASEURL.BGG_EXPANSION.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("boardgameexpands", $items)) :

		$row_title = __('Expands', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgameexpands"] as $item) {
				
				$url = BGG_BASEURL.BGG_BOARDGAME.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("boardgamefamily", $items)) :

		$row_title = __('Family', 'boardgamegeek');
		$row_content = "";

		$sep = "";
		foreach ($items["boardgamefamily"] as $item) {
				
				$url = BGG_BASEURL.BGG_FAMILY.$item->id;
				$row_content .= $sep . '<a href="'.$url.'" target="blank">'. $item->value . "</a>";
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);							
	endif;
	
	if (array_key_exists("primary", $names)) {
		
		$row_title = __('Primary Name', 'boardgamegeek');
		$row_content = $names["primary"][0]->value;
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	}
	
	if (array_key_exists("alternate", $names)) {
		
		$row_title = __('Alternate Names', 'boardgamegeek');
		$row_content = "";
		
		$sep = "";
		foreach ($names["alternate"] as $item) {
				
				$row_content .= $sep . $item->value;
				$sep = ", ";	
		}
		
		$html_code .= sprintf($row, 
	 						$row_title,
	 						$row_content
							);
	}
	
	$html_code .= '</tbody></table>';
	
	$html_code .= '<p class="bggapi-courtesy">Infos courtesy of <a href="'. BGG_BASEURL .'" target="_blank>boardgamegeek.com</a>. <a href="'. BGG_BASEURL.BGG_BOARDGAME.$id .'" target="_blank">More Infos</a>.</p>';
	
 	return $html_code;	
 	
?>
			</tbody>
		</table>
			
<?php			
		
		} else {
			echo "<p class=\"bggapi-nodata\">No data from BGG.</p>";
		}
		
	}

	return $html_code;
	
}

add_shortcode( 'bgg' , 'bgg_shortcode' );