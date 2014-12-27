<?php

/**
 * Performs commands for the search engine.
 * @author : Ramzi Sh. Alqrainy - ramzi.alqrainy@gmail.com
 * @copyright Copyright (c) 2015
 * @version 0.2
 */
class SearchCommand extends CConsoleCommand {
	private static $lock = null;
	private static $lock_fn = null;
	private static $lock_fl = null;
	private static $dataSolr = 0;
	
	/**
	 * getDBConnection is a function that connect with DB
	 *
	 * @return CDbConnection
	 */
	protected static function getDbConnection() {
		// Returns the application components.
		$component = Yii::app ()->getComponents ( false );
		if (! isset ( $component ['readonlydb'] ))
			return Yii::app ()->db;
		if (! Yii::app ()->readonlydb instanceof CDbConnection)
			return Yii::app ()->db;
		return Yii::app ()->readonlydb;
	}
	
	/**
	 * lock_aquire is a function that prevent other commands run if any commands is running
	 *
	 * @param <type> $t        	
	 */
	private static function lock_aquire($t = null) {
		if ($t === null)
			$t = LOCK_EX | LOCK_NB;
		self::$lock_fn = dirname ( __FILE__ ) . "/../runtime/search.lock";
		// Opens file.
		$fh = fopen ( self::$lock_fn, 'w+' );
		// Changes file mode
		@chmod ( self::$lock_fn, 0777 );
		if (! flock ( $fh, $t ))
			return null;
		self::$lock = $fh;
		return $fh;
	}
	
	/**
	 * lock_release is a function that remove the lock.
	 *
	 * @return <type>
	 */
	private static function lock_release() {
		if (! self::$lock_fn)
			return - 1;
			// Portable advisory file locking
		flock ( self::$lock, LOCK_UN );
		// Closes an open file pointer
		fclose ( self::$lock );
		// Deletes a file
		@unlink ( self::$lock_fn );
		return 0;
	}
	
	/**
	 * Create file
	 *
	 * @param <srting> $collection        	
	 */
	private static function create_unavailable_file($collection) {
		$filename = Yii::app ()->basePath . "/runtime/search_engine_down_$collection.txt";
		@touch ( $filename );
	}
	
	/**
	 * Delete file
	 *
	 * @param <string> $core        	
	 */
	private static function delete_unavailable_file($collection) {
		$filename = Yii::app ()->basePath . "/runtime/search_engine_down_$collection.txt";
		unlink ( $filename );
	}
	private static function prepareSolrDocument($object, $limit = 1000) {
		$document = array ();		
		$document ['id'] = ( int ) $object ['song_id'];
		if (isset ( $object ['song_title'] )){
			$document ['song_title_text'] = $object ['song_title'];
			$document ['song_title_str'] = $object ['song_title'];
			$document ['autocomplete_mul'][] = $object ['song_title'];
		}
		if (isset ( $object ['song_year'] ))         $document ['song_year_int'] = $object ['song_year'];
		if (isset ( $object ['song_popularity'] ))   $document ['song_popularity_int'] = $object ['song_popularity'];
		if (isset ( $object ['song_url'] ))          $document ['song_url_str'] = $object ['song_url'];
		if (isset ( $object ['album_name'] ))        $document ['album_name_text'] = $object ['album_name'];
		if (isset ( $object ['artist_name'] )){
			$document ['artist_name_text'] = $object ['artist_name'];
			$document ['artist_name_str'] = $object ['artist_name'];
			$document ['autocomplete_mul'][] = $object ['artist_name'];
		}
		if (isset ( $object ['artist_type'] )){
			$document ['artist_type_text'] = $object ['artist_type'];
			$document ['artist_type_str'] = $object ['artist_type'];
			$document ['autocomplete_mul'][] = $object ['artist_type'];
		}
		if (isset ( $object ['artist_year'] ))       $document ['artist_year_int'] = $object ['artist_year'];
		if (isset ( $object ['artist_url'] ))        $document ['artist_url_str'] = $object ['artist_url'];
		if (isset ( $object ['artist_img_url'] ))    $document ['artist_img_url_str'] = $object ['artist_img_url'];
		if (isset ( $object ['artist_popularity'] )) $document ['artist_popularity_int'] = $object ['artist_popularity'];
		if (isset ( $object ['artist_yrank'] ))      $document ['artist_yrank_int'] = $object ['artist_yrank'];
		if (isset ( $object ['source'] ))            $document ['source_str'] = $object ['source'];
		if (isset ( $object ['lyrics'] ) ){
			if(strlen($object ['lyrics'])>3600)$object ['lyrics']=substr($object ['lyrics'],3600);
			$document ['lyrics_str'] = $object ['lyrics'];
			$document ['lyrics_text'] = $object ['lyrics'];
		}
				
		if(isset($document ['autocomplete_mul'])) $document['text'] = $document ['autocomplete_mul'];
		
				
		$collection = "collection1";
		try{
			Yii::app ()->$collection->updateOneWithoutCommit ( $document );
		}catch (Exception $e){
			unset($document ['lyrics_str']);
			unset($document ['lyrics_text']);
			Yii::app ()->$collection->updateOneWithoutCommit ( $document );
		}
		
		if (self::$dataSolr >= $limit) {
			self::$dataSolr = 0;
			Yii::app ()->$collection->solrCommitWithOptimize ();
		} else {
			self::$dataSolr ++;
		}
	}
	
	/**
	 * Index all posts and it's info
	 *
	 * @param <type> $mtime        	
	 * @param <type> $city        	
	 * @param <type> $limit        	
	 * @param <type> $core        	
	 */
	public static function fillData($mtime, $limit, $collection = 1, $actionType = null, $sleep = 0, $song_ids = array()) {
		switch ($actionType) {
			case "fullReindex" :
			case "reindex" :
				// locking the command
				print "locking ... \n";
				if (! self::lock_aquire ()) {
					die ( " ** could not acuire lock!!\n" );
				}
				// Sets access and modification time of file , If the file does not exist, it will be created.
				self::create_unavailable_file ( $collection );
				break;
		}
		
		$last_id = - 1;
		// Get count of data that will be indexed .
		
		if ($mtime == - 1) {
			$mtime = "2013-01-01 10:10:10";
		}
		$condition = "";
		if ($actionType == "update") {
			$condition = "and song.lastUpdatedDate > '$mtime'  ";
		}
		
		
		$sql = " select 
				 count(song.id) as count 
				 from song left join Artist as artist on song.artistid=artist.id
				 left join albums on song.albumid = albums.id   
				 left join best_sources on song.best_source_id=best_sources.id 
				 where song.id>-1 $condition
				 order by song.id limit 1;
		";
		
		$count = self::getDbConnection ()->createCommand ( $sql )->queryAll ();
		
		$count = $count [0] ["count"];
		
		// Print count of data.
		print "To be indexed (" . $count . ") songs" . chr ( 10 );
		
		// initialize variable .
		$done_count = 0;
		$coll = "collection" . $collection;
		
		$size_of_data = 0;
		$done = false;
		$offset = 0;
		
		while ( ! $done ) {		
			// Build Indexing Query
			$sql = "
        		 select
				 song.id as song_id,
				 song.song as song_title,
				 song.year as song_year,
				 song.popularity as song_popularity,
				 song.url as song_url,
				 album_name,
				 artist.name as artist_name,
				 type as artist_type,
				 artist.year as artist_year,
				 artist.url as artist_url,
				 artist.img_url as artist_img_url,
				 artist.popularity as artist_popularity,
				 artist.yrank as artist_yrank,
				 lyrics.lyrics as lyrics,
				 song.lastUpdatedDate as lastUpdatedDate,
				 best_sources.source as source from song left join Artist as artist on song.artistid=artist.id
				 left join albums on song.albumid = albums.id
				 left join best_sources on song.best_source_id=best_sources.id
				 left join lyrics on song.lyricsid=lyrics.id
				 where song.id > $last_id $condition
				 order by song.id limit $limit;";
			
			// execute the indexing query
			$songs = self::getDbConnection ()->createCommand ( $sql )->queryAll ();
			// Get size of rows
			$size_of_data = sizeof ( $songs );
			if ($size_of_data) {
				foreach ( $songs as $song ) {
					
					self::prepareSolrDocument ( $song, $limit );
					$last_id = $song ['song_id'];
					$mtime = $song ['lastUpdatedDate'];
					
				}
				Yii::app ()->collection1->solrCommitWithOptimize ();
			} // end foreach for Indexing process .
			$done_count += $size_of_data;
			
			if ($count > 0) {
				printf ( "** done  with %d posts, overall %g %%\n", $size_of_data, 100.0 * $done_count / $count );
			} else {
				print "No update posts \n";
			}
			$done = ($size_of_data < $limit);
			$offset ++;
		} // end while of objects
		  // execute sleep .
		sleep ( $sleep );
		
		$coll = "collection" . $collection;
		if ($size_of_data && $actionType != "update") {
			Yii::app ()->$coll->solrCommitWithOptimize ();
		}
		
		if ($size_of_data && $actionType == "update") {
			Yii::app ()->$coll->solrCommit ();
		}
		
		print "\n\n";
		
		$filename = Yii::app ()->basePath . "/runtime/search_" . $collection . ".txt";
		touch ( $filename );
		file_put_contents ( $filename, $mtime );

		Print "\nDone \n";
	}
	
	/**
	 * This can be useful when (backwards compatible) changes have been made to solrconfig.xml or schema.xml files
	 *
	 * @param <int> $core
	 *        	= 1 , 2 , 3
	 */
	public static function actionReload($collection = 1) {
		try {
			if (is_numeric ( $collection )) {
				shell_exec ( "wget -O - 'http://localhost:8983/solr/admin/cores?action=RELOAD&core=collection" . $collection . "'   1>/dev/null 2>/dev/null" );
			}
			
			print "\ndone\n";
		} catch ( Exception $e ) {
			print "Error : " . $e->getMessage ();
		}
	}
	
	/**
	 * actionUpdate indexing the new data
	 *
	 * @author Ramzi Sh. Alqrainy
	 * @param <int> $limit        	
	 * @param <int> $core
	 * @param <int> $sleep        	
	 */
	public function actionUpdate($limit = 3000, $collection = 1, $sleep = 0) {
		$time = time ();
		$mtime = 0;
		$filename = Yii::app ()->basePath . "/runtime/search_" . $collection . ".txt";
		// Reads entire file into a string.
		$contents = file_get_contents ( $filename );
		if ($contents == false) {
			$mtime = - 1;
		} else {
			$mtime = ($contents);
		}
		self::fillData ( $mtime, $limit, $collection, 'update', $sleep );
	}
	
	/**
	 * actionFullReindex rebuild schema after remove it and indexing the data
	 *
	 * @author Ramzi Sh. Alqrainy
	 * @param <type> $city        	
	 * @param <type> $limit        	
	 * @param <type> $collection        	
	 */
	public function actionFullReindex($limit = 3000, $collection = 1, $sleep = 0) {
		$q = "*:*";
		$coll = "collection" . $collection;
		Yii::app ()->$coll->rm ( $q );
		self::fillData ( - 1, $limit, $collection, 'fullReindex', $sleep );
	}
	
	/**
	 * actionReindex re indexing the data
	 *
	 * @param <int> $limit        	
	 * @param <int> $collection        	
	 * @param <int> $sleep=0        	
	 */
	public function actionReindex($limit = 3000, $collection = 12, $sleep = 0) {
		self::fillData ( - 1, $limit, $collection, 'reindex', $sleep );
	}
	public function actionReindexSongByID($collection = 1, $song_id = 0) {
		self::deleteSongByID ( array (
				$song_id 
		), $collection );
		$sql = "
        		 select
				 song.id as song_id,
				 song.song as song_title,
				 song.year as song_year,
				 song.popularity as song_popularity,
				 song.url as song_url,
				 album_name,
				 artist.name as artist_name,
				 type as artist_type,
				 artist.year as artist_year,
				 artist.url as artist_url,
				 artist.img_url as artist_img_url,
				 artist.popularity as artist_popularity,
				 artist.yrank as artist_yrank,
				 lyrics.lyrics as lyrics,
				 best_sources.source as source from song left join Artist as artist on song.artistid=artist.id
				 left join albums on song.albumid = albums.id
				 left join best_sources on song.best_source_id=best_sources.id
				 left join lyrics on song.lyricsid=lyrics.id
				 where song.id = $song_id 
				 order by song.id limit 1;";
			
			// execute the indexing query
			$songs = self::getDbConnection ()->createCommand ( $sql )->queryAll ();
			// Get size of rows
			$size_of_data = sizeof ( $songs );
			if ($size_of_data) {
				foreach ( $songs as $song ) {
					
					self::prepareSolrDocument ( $song, 1 );
					$last_id = $song ['song_id'];
					
				}
				Yii::app ()->collection1->solrCommitWithOptimize ();
			} // end foreach for Indexing process .
			
	}
	
	/**
	 * actionDeleteSongByID action delete song with specific id from solr
	 *
	 * @param <int> $collection        	
	 */
	public static function actionDeleteSongByID($collection = 1, $song_id = 0) {
		fwrite ( STDOUT, "This command will delete song #$song_id from your corpora and there is no undo, Are you sure ?(y/n)\n" );
		// get input
		$answer = trim ( fgets ( STDIN ) );
		if ($answer == "y") {
			self::deleteSongByID ( array (
					$song_id 
			), $collection );
		} else {
			print "So, please be carefull ;) \n";
		}
	}
	
	/**
	 */
	public static function deleteSongByID($song_ids = array(), $collection = 1) {
		print "delete song(s) of collection $collection ... \n";
		
		$coll = "collection" . $collection;
		foreach ( $song_ids as $song_id ) {
			$q = "id:$song_id";
			print " id : " . $song_id;
			Yii::app ()->$coll->rm ( $q );
		}
		print "\r[Done]\n";
	}
	
	/**
	 * actionClear action clears all data from solr
	 *
	 * @param <int> $collection        	
	 */
	public function actionClearSongs($collection = 1) {
		fwrite ( STDOUT, "This command will delete all data from your corpora and there is no undo, Are you sure ?(y/n)\n" );
		// get input
		$answer = trim ( fgets ( STDIN ) );
		if ($answer == "y") {
			print "clearing data of collection $collection ... \n";
			$q = "*:*";
			$coll = "collection" . $collection;
			Yii::app ()->$coll->rm ( $q );
			print "\r[Done]\n";
		} else {
			print "So, please be carefull ;) \n";
		}
	}
	
	/**
	 * actionIndex to show the info.
	 * search commands.
	 *
	 * @author Ramzi Sh. Alqrainy
	 */
	public function actionIndex() {
		echo "
--limit = Number \n\t use it if you want to increase/decrease the amount of data that will \n\t be withdrawn from the Database and stored in Solr at each loop until \n\t the end of all data , this is optional by default limit=3000\n
--collection = Number \n\t use it if you want to perform method on specfic solr component \n\t  , this is optional by default \n\t performs the method on one shard solr.\n ";
		echo $this->getHelp ();
	}
	
	/**
	 * Represents a response to a ping request to the server
	 *
	 * @param <int> $collection        	
	 */
	public function actionPing($collection = 1) {
		print "Collection $collection \n";
		$coll = "collection" . $collection;
		var_dump ( Yii::app ()->$coll->_solr->ping () );
	}
	
	/**
	 * Defragments the index
	 *
	 * @param <int> $collection        	
	 */
	public function actionOptimize($collection = 1) {
		print "Collection $collection \n";
		$coll = "collection" . $collection;
		var_dump ( Yii::app ()->$coll->_solr->optimize () );
	}
}