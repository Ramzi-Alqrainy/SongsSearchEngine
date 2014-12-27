<?php

class TrevxController extends Controller
{
	public function actionIndex()
	{
		$this->render('index');
	}
	
	public function actionSearch($q=null,$page=1,$format='json')
	{
	    $q = urldecode($q);
		$params['result_per_page'] = isset($_GET['result_per_page']) ? (int)$_GET['result_per_page'] : 10;
		
		// When you assign mm (Minimum 'Should' Match), we remove q.op
		// becuase we can't set two params to the same function
		// q.op=AND == mm=100% | q.op=OR == mm=0%
		$params['mm'] = isset($_GET['mm']) ? $_GET['mm'] : '75%';
		$params['sort'] = isset($_GET['sort']) ? $_GET['sort'] : null;
		if(isset($_GET['sort'])){
                        if($_GET['sort']=='song_title asc'){
				$params['sort'] = "song_title_str asc";
			}
			if($_GET['sort']=='song_title desc'){
 				$params['sort'] = "song_title_str desc";
			}
		}
		if(isset($_GET['show_facet'])){
			if($_GET['show_facet'] == 'true')$params['show_facet'] = true;
		}

		$params['category'] = isset($_GET['category']) ? $_GET['category'] : null;
		$params['year'] = isset($_GET['year']) ? $_GET['year'] : null;
		$params['artist'] = isset($_GET['artist']) ? $_GET['artist'] : null;
		
		switch($format){
			case 'php':
				var_dump(SearchLib::get($q,$page,$params));die();
				break;
			case 'json':
				print json_encode(SearchLib::get($q,$page,$params));
				break;
		}
		
	}
	
	public function actionAutocomplete($q=null,$format='json')
	{
		$q = urldecode($q);
		$params['result_per_page'] = isset($_GET['result_per_page']) ? (int)$_GET['result_per_page'] : 10;
	
		$params = array();
		switch($format){
		case 'php':
			var_dump(SearchLib::autocomplete($q,$params));die();
			break;
		case 'json':
			print json_encode(SearchLib::autocomplete($q,$params));
			break;
		}
	
	}
	
	/**
	 * 
	 * @param string $content
	 * @param string $format
	 */
	public function actionRelatedSongs($content=null,$format='json')
	{
		$q = urldecode($content);
		$params['result_per_page'] = isset($_GET['result_per_page']) ? (int)$_GET['result_per_page'] : 10;
		// When you assign mm (Minimum 'Should' Match), we remove q.op
		// becuase we can't set two params to the same function
		// q.op=AND == mm=100% | q.op=OR == mm=0%
		$params['mm'] = isset($_GET['mm']) ? $_GET['mm'] : '95%';
	
		$params = array();
		switch($format){
			case 'php':
				var_dump(SearchLib::getRelatedSongs($q,$params));die();
				break;
			case 'json':
				print json_encode(SearchLib::getRelatedSongs($q,$params));
				break;
		}
	
	}
	
	

}
