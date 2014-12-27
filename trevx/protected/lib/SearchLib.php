<?php
include('I18N/Arabic.php');
    /**
     * Class Search performs many functions about search.
     *
     * @author : Ramzi Sh. Alqrainy ramzi.alqrainy@gmail.com
     * @copyright Copyright (c) 2015
     * @see 
     * @version 1.2
     */
    class SearchLib {

        /**
         * get : is a main function that handling search process.
         * with more information and detials about collections, please visit assembla wiki "Search APIs"
         * @param  $q
         * @param  $page
         * @param  $params
         * @version 3.1
         */
        public static function get($q = null, $page = '1', $params = array()) {
            // Construct the solr query and handling the parameters
            $preexecute = self::constructQuery($q, $page, $params);
            $q = $preexecute['q'];
            $options = $preexecute['options'];
            $results_per_page = $preexecute['results_per_page'];
            $offset = $preexecute['offset'];

            // Get Results from Solr.
            $results = self::execute($q, $offset, $results_per_page, $options);
          
            // Change the query to show to the user empty.
            if ($q === '*:*') {
                $q = "";
                $results->q = null;
            }
            
            // prepare results
            return self::prepareResults($results);
        }

        /**
         *
         * constructQuery : constructs the query and handling the user params
         * @param  $q
         * @param  $page
         * @param  $country_id
         * @param  $params
         */
        public static function constructQuery($q, $page, $params) {
            ####### Check search parameter #####
            // Strip whitespace (or other characters) from the beginning and end of a string
            $q = trim($q);
            $params['result_per_page'] = isset($params['result_per_page']) ? $params['result_per_page'] : 20;
            $params['q.op'] = isset($params['q.op']) ? $params['q.op'] : 'AND';
            $params['offset'] = isset($params['offset']) ? $params['offset'] : false;
            $show_facet = isset($params['show_facet']) ? $params['show_facet'] : false;
            $category = isset($params['category']) ? $params['category'] : false;
            $year = isset($params['year']) ? $params['year'] : false;
            $artist = isset($params['artist']) ? $params['artist'] : false;
            $sort = isset($params['sort']) ? $params['sort'] : false;

            ###################################
            $page = (int)$page;
            if ($page < 1) $page = 1;
            if ($params['offset'] === false) {
                $offset = ($page - 1) * $params['result_per_page'];
            }

            // Build Solr query
            $options = array(
            		 'spellcheck' => "true",
            		 'qt' => '/spell',
            		 'spellcheck.collate' => 'true',
            		 'spellcheck.maxCollationTries' => '17',
            		 'spellcheck.accuracy' => '0.7',
            		 'spellcheck.count' => '18',
            		 'spellcheck.extendedResults' => 'true',
            		 'spellcheck.onlyMorePopular' => 'true',
            		 'qf' => 'album_name_text song_title_text^10 artist_name_text artist_type_text ',
            		 'q.op' => $params['q.op'],
            		 'bf' => "product(song_popularity_int,1)^5",
            		 'defType' => 'edismax',
            		 'hl'=>'true',
            		 'hl.fl'=>'lyrics_text',
            		 'hl.simple.pre'=>'***',
            		 'hl.simple.post' =>'***',
            );


            // When you assign mm (Minimum 'Should' Match), we remove q.op
            // becuase we can't set two params to the same function
            // q.op=AND == mm=100% | q.op=OR == mm=0%
            if (isset($params['mm'])) {
                unset($options['q.op']);
                $options['mm'] = $params['mm'];
            }

            if ($show_facet) {
                $options['facet'] = 'true'; // enables facet counts in the query response
                $options['facet.sort'] = 'count';
                $options['facet.mincount'] = '1';
                $options['facet.limit'] = '5'; // Controls how many constraints should be returned for each facet.
                // facet.field param allows you to specify a field which should be treated as a facet.
                $options['facet.field'] = array('song_year_int','artist_type_str','artist_name_str');
            }
            // For empty query, get all results
            if (!$q || empty($q)) $q = '*:*';
          
            // Filter by category name
            if ($category) {
                $options["fq"][] = 'artist_type_str:"' . $category . '"';
                $options["facet"] = "true";
                $options["facet.field"][] = 'artist_type_str';
            }
         
            // Filter by song year
            if ($year) {
            	$options["fq"][] = 'song_year_int:"' . $year . '"';
            	$options["facet"] = "true";
            	$options["facet.field"][] = 'song_year_int';
            }
            
            // Filter by artsist name
            if ($artist) {
            	$options["fq"][] = 'artist_name_str:"' . $artist . '"';
            	$options["facet"] = "true";
            	$options["facet.field"][] = 'artist_name_str';
            }
            
            // Sort
            if ($sort) {
                $options['sort'] = $sort;
            }

            // prepare returning arrays
            $processing = array();
            $processing['q'] = $q;
            $processing['options'] = $options;
            $processing['results_per_page'] = $params['result_per_page'];
            $processing['offset'] = $offset;
          
            return $processing;
        }

        /**
         *
         * prepareResults : Prepare the results and build mapping between Solr and Application Level
         * @param $results
         * @param $ignore_id
         */
        public static function prepareResults($results) {
            $results_info = array();
            $results_info['num_of_results'] = $results->response->numFound;
            
            $results_info['spellchecking_words'] = "";
            if($results->response->numFound==0 && isset($results->spellcheck->suggestions->collation->collationQuery)){
            	$results_info['spellchecking_words'] = $results->spellcheck->suggestions->collation->collationQuery;
            }
           
            $documents = array();
            if ($results->response->numFound > 0 && $results->response->docs != null) {
                $i = 0;
                foreach ($results->response->docs as $doc) {
                    $document = new stdClass();
                    $id = $doc->id;
                    $document->id = $id;
                    $document->song_title = $doc->song_title_text;
                    $document->lyrics_highlight = null;
                    if (isset($results->highlighting->$id->lyrics_text)) {
                        foreach ($results->highlighting->$id->lyrics_text as $highlight) {
                            $document->lyrics_highlight = $highlight;
                            break;
                        }
                    }
                    $document->song_year = isset($doc->song_year_int) ? $doc->song_year_int : 0;
                    $document->song_popularity = $doc->song_popularity_int;
                    $document->song_url = $doc->song_url_str;
                    $document->album_name = $doc->album_name_text;
                    $document->artist_name = $doc->artist_name_text;
                    $document->artist_type = $doc->artist_type_text;
                    $document->artist_year = $doc->artist_year_int;

                    $document->artist_url = $doc->artist_url_str;
                    $document->artist_img_url = $doc->artist_img_url_str;
                    $document->artist_popularity = $doc->artist_popularity_int;

                    $document->artist_yrank = isset($doc->artist_yrank_int) ? $doc->artist_yrank_int : null;
                    $i++;
                    $documents[] = $document;
                }
            }
            $results_info['results'] = $documents;

            $facetd_fields = array('song_year_int' => 'song_year','artist_type_str'=>'artist_type','artist_name_str'=>'artist_name');
            $facetd_search = array();

            foreach ($facetd_fields as $facet => $type) {
                if (isset($results->facet_counts->facet_fields->$facet)) {
                    foreach ($results->facet_counts->facet_fields->$facet as $tag => $count) {
                    	$facetd_search[$facet][] = array('tag'=>$tag,"count"=>$count);
                    }
                }
            }


            $results_info['faceted_search'] = $facetd_search;

            return $results_info;
        }


        /**
         *
         * Related Songs function returns related songs for specific content.
         * @param  $content
         * @param  $params
         * @version 0.1
         * @author Ramzi Sh. Alqrainy
         *
         */
        public static function getRelatedSongs($content = null, $params = array()) {
            // Construct the solr query and handling the parameters
            $params['resultPerPage'] = isset($params['resultPerPage']) ? $params['resultPerPage'] : 3;
            if (!isset($params['mm'])) {
                $params['mm'] = "95%";
            }
            
            $preexecute = self::constructQuery($content, 1, $params);
            $q = $preexecute['q'];
            $options = $preexecute['options'];
            $results_per_page = $preexecute['results_per_page'];
            $offset = $preexecute['offset'];
            // Get Results from Solr.
            $results = self::execute($q, $offset, $results_per_page, $options);

            // prepare results
            return self::prepareResults($results);
        }

      
       /**
        * 
        * @param string $q
        * @param $params
        * @returnmultitype:unknown
        */
        public static function autocomplete($q = null, $params = array()) {
            // Construct the solr query and handling the parameters
            $params['resultPerPage'] = isset($params['resultPerPage']) ? $params['resultPerPage'] : 5;
            $options = array('spellcheck' => "true", 'qt' => '/spell', 'spellcheck.collate' => 'true',
            		 'spellcheck.maxCollationTries' => '17', 'spellcheck.accuracy' => '0.6',
            		 'spellcheck.count' => '18', 'spellcheck.extendedResults' => 'true',
            		 'spellcheck.onlyMorePopular' => 'true', 'facet' => 'true', 'facet.field' => 'autocomplete_mul',
            		 'facet.mincount' => '1', 'facet.sort' => 'count', 'facet.prefix' => $q);
            // Get Results from Solr.
            $results = self::execute("*:*", 0, $params['resultPerPage'], $options);
            $terms = array();
            $count_facet = 0;
            
            $facetd_fields = array('autocomplete_mul' => 'autocomplete_mul');
            
            foreach ($facetd_fields as $facet => $type) {
            	if (isset($results->facet_counts->facet_fields->$facet)) {
            		foreach ($results->facet_counts->facet_fields->$facet as $tag => $count) {
            			$terms[] = $tag;
                    	if ($count_facet > 6) break;
                    	$count_facet++;
            		}
            	}
            }


            if (count($terms) == 0 && !isset($params['repeat'])) {
                $params['resultPerPage'] = isset($params['resultPerPage']) ? $params['resultPerPage'] : 5;
                $options = array('spellcheck' => "true", 'qt' => '/spell', 'spellcheck.collate' => 'true',
                		 'spellcheck.maxCollationTries' => '17', 'spellcheck.accuracy' => '0.6',
                		 'spellcheck.count' => '18', 'spellcheck.extendedResults' => 'true',
                		 'spellcheck.onlyMorePopular' => 'true', 'facet' => 'true', 'facet.field' => array('autocomplete_mul'),
                		 'facet.mincount' => '1', 'facet.sort' => 'count',
                		 'facet.prefix' => $q);
                // Get Results from Solr.
                $results = self::execute($q, 0, $params['resultPerPage'], $options);

                if (isset($results->spellcheck->suggestions->collation->collationQuery)) {
                    $params['repeat'] = false;
                    $terms = self::autocomplete($results->spellcheck->suggestions->collation->collationQuery, $params);
                    if (!count($terms)) array_unshift($terms, $results->spellcheck->suggestions->collation->collationQuery);
                }
            }
            
            if (!count($terms) && !isset($params['repeat'])) {
                $obj = new I18N_Arabic('KeySwap');
                $text = $obj->swap_ea($q);
                $params['is_search'] = false;
                $res = self::get($text, 1,$params);

                if ($res['num_of_results']) {
                    $params['repeat'] = false;
                    $terms = self::autocomplete($text, $country_id, $params);
                    if (!count($terms)) array_unshift($terms, $text);
                } else {
                    $text = $obj->swap_ae($q);
                    $params['is_search'] = false;
                    $res = self::get($text, 1,$params);
                    if ($res['num_of_results']) {
                        $params['repeat'] = false;
                        $terms = self::autocomplete($text, $params);
                        if (!count($terms)) array_unshift($terms, $text);
                    }
                }
            }

            return $terms;
        }
   

        /**
         * execute : get results according what is the solr collection running
         * with more information and detials about collections, please visit assembla wiki "Solr Collections"
         * @author Ramzi Sh. Alqrainy
         * @param  $query
         * @param  $offset
         * @param  $limit
         * @param  $options
         * @return results
         */
        public static function execute($query, $offset, $limit, $options) {
            return Yii::app()->collection1->get($query, $offset, $limit, $options);
            throw new CHttpException(500, 'No Collection' . $collection . ' Available');
        }
        
      
       

    }
    
