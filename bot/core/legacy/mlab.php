<?php

function mlab_getDocument($doc_id){
	$ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL, 'https://api.mongolab.com/api/1/databases/' . vars_get('MLAB_DBNAME') . '/collections/' . $doc_id . '?apiKey=' . vars_get('MLAB_TOKEN'));
	  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	      'Content-Type: application/json'
	  ));
	  $res = curl_exec($ch);
	  curl_close($ch);
	  return json_decode($res, true)[0];
}
function mlab_existsDocument($doc_id){
	$ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL, 'https://api.mongolab.com/api/1/databases/' . vars_get('MLAB_DBNAME') . '/collections/' . $doc_id . '?apiKey=' . vars_get('MLAB_TOKEN'));
	  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Content-Type: application/json'
	  ));
	  $res = curl_exec($ch);
	  curl_close($ch);
	if(count(json_decode($res)) > 0){
		return true;
	} else {
		return false;
	}
}
function mlab_updateDocument($doc_id, $doc_data){
	if (mlab_existsDocument($doc_id)) {
		  $ch = curl_init();
		  curl_setopt($ch, CURLOPT_URL, "https://api.mongolab.com/api/1/databases/" . vars_get('MLAB_DBNAME') . "/collections/" . $doc_id . "?apiKey=" . vars_get('MLAB_TOKEN'));
		  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc_data));
	  	  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	      	'Content-Type: application/json'
	      ));
		  $res = curl_exec($ch);
		  curl_close($ch);
		  return $res;
	} else {
		  $ch = curl_init();
		  curl_setopt($ch, CURLOPT_URL, 'https://api.mongolab.com/api/1/databases/' . vars_get('MLAB_DBNAME') . '/collections/' . $doc_id . '?apiKey=' . vars_get('MLAB_TOKEN'));
		  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt($ch, CURLOPT_POST, 1);
		  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc_data));
		  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		      'Content-Type: application/json'
		  ));
		  $res = curl_exec($ch);
		  curl_close($ch);
		  return $res;
	}
}

?>