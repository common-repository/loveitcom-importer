<?php
/**
 * WordPress eXtended RSS file parser implementations
 *
 * @package WordPress
 * @subpackage LoveIt Importer
 */

/**
 * HTML Parser that makes use of the SimpleXML PHP extension.
 */
class LoveItGalleryParser {
    
    var $pin_class="pin-container";
    //loveit metas
    var $loveit_data_to_save = array(
        'data-image_id',
        'data-media_type',
        'data-media_subtype',
        'data-media_external_id',
        'data-pin_board_id',
        'data-pin_id',
        'data-pin_target',
        'data-pin_user_id'
    );

	function parse( $file ) {

		$all_authors = $posts = $all_terms = array();

		$internal_errors = libxml_use_internal_errors(true);
                
                $html = file_get_contents($file);
                $doc = new DOMDocument();
                
                $doc->validateOnParse = true;
                $doc->preserveWhiteSpace = false;
                
                // halt if loading produces an error
                if (!$doc->loadHTML($html)){
                    return new WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this HTML file', 'wordpress-importer' ));
                }

                //get pins

                $path_a = new DOMXPath($doc);
                $pins = $path_a->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $this->pin_class ')]");
                $total_pins = $pins->length;
 
                foreach ($pins as $key=>$pin) {
                    
                    //parse pin attributes
                
                    foreach ($pin->attributes as $attrName => $attrNode) {
                        $pins_arr[$key][$attrName] = $attrNode->nodeValue;
                    }

                    //USERNAME & ALBUM
                    
                    //get the element where are stored the links to the username & album, for this pin
                    $pin_p_els = $pin->getElementsByTagName('p');
                    $pin_p_el = $pin_p_els->item(0);

                    if ($pin_p_el){
                        $pin_links = $pin_p_el->getElementsByTagName('a');
                        
                        $pin_username_link = $pin_links->item(0);
                        $pin_album_link = $pin_links->item(1);
                        
                        //username
                        if($pin_username_link){
                            $pins_arr[$key]['data-pin_user_name'] = $pin_username_link->nodeValue;
                        }
                        
                        //username
                        if($pin_album_link){
                            $pins_arr[$key]['data-pin_board_name'] = $pin_album_link->nodeValue;
                        }
                        
                    }
                    
                    
                }
 
                if (!$pins_arr){
                    return new WP_Error( 'SimpleXML_parse_error', __( 'No pins were found', 'wordpress-importer' ));
                }
                
                // grab posts
                $blank_post = $this->blank_post();
                
                // create or get the root category
                $root_category_id = LoveItDotComImporter::get_term_id('LoveIt.com','category');

		foreach ($pins_arr as $pin ) {
                    
                        $import_id = $pin['data-pin_id'];

			$post = array(
				'post_title' => (string) $pin['data-pin_description'],
				'post_author' => (string) $pin['data-pin_user_name']
			);
                        
			$post = wp_parse_args($post,$blank_post);
                        
                        //post metas
                        foreach($pin as $meta_key=>$value){
                            $post['loveit_data'][$meta_key] = (string)$value; //add for later use
                            
                            if(!in_array($meta_key,$this->loveit_data_to_save)) continue; //not important to save
                            $meta_key = str_replace("data-","_loveit-",$meta_key);
                            $post['postmeta'][$meta_key]=$value;
                            
                        }
                        
                        //post category
                        $post['terms'][] = array(
                                'term_name'=>$pin['data-pin_board_name'],
                                'term_taxonomy'=>'category',
                                'term_args'=>array('parent'=>$root_category_id) //child of root category
                        );
                        
                        $posts[$import_id] = $post;
		}

		foreach ((array)$posts as $post ) {
                    
                    // grab authors
                    $username = $post['post_author'];
                    $login = (string) sanitize_title($username);
                    $all_authors[] = array(
                            'author_login' => $login,
                            'author_display_name' => (string) $username
                    );
                    
                    // grab terms
                    foreach((array)$post['terms'] as $term){
                        $all_terms[]=$term;
                    }
		}
                
                //order chronologically
                $posts = array_reverse($posts,true);
                //$posts = array_slice($posts, 0,5,true); //FOR DEBUG

		$parsed =  array(
			'authors' => array_unique($all_authors, SORT_REGULAR),
			'posts' => $posts,
			'terms' => array_unique($all_terms, SORT_REGULAR)
		);
                
                return $parsed;
                
	}
        
    function blank_post(){
        $post=array(
            'post_type'=>'post',
            'post_status'=>'publish',
            'post_date'=>current_time('mysql'),
            'post_date_gmt'=>current_time('mysql',1)
        );
        return $post;
    }
}

