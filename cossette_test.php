<?php

/**
 * AUTHOR: Alex Dunn
 * REQUIREMENTS:
 * 		- PHP7
 *      - php-curl
 *      - php-dom
 */

// if no city parameter given, display program usage instructions
if ($argc < 2) {
    exit("Usage: php music_search.php <city>\n");
}

// store city string
if (isset($argv)) {
	$city = urlencode($argv[1]);
}

// request url
$url = 'https://musicbrainz.org/ws/2/artist/?fmt=json&limit=10&query=area:' . $city;

// make request using cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERAGENT, 'MusicBrainzTest/1.0'); // set user agent
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't print response

$data = curl_exec($ch);

$error_msg = NULL;
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
}

curl_close($ch);

// check curl errors
if (!is_null($error_msg)) {
	exit("Error: " . $error_msg . "\n");
}

// parse json response
if (!isset($data) || is_null($data) || strlen($data) <= 2) {
	exit("No results\n");
}

$data = json_decode($data, true); // return json as array

if (isset($data['error'])) {
	exit('Error: ' . $data['error'] . "\n");
}

$artists = $data['artists'];

// build xml and csv
$xml = new DOMDocument();
$title = 'MusicBrainzResults';
$root = $xml->appendChild($xml->createElement("results"));
$root->appendChild($xml->createElement("title", $title));
$root->appendChild($xml->createElement("totalRows", sizeof($artists)));
$xmlArtists = $root->appendChild($xml->createElement('artists'));

$csv = fopen(str_replace(' ', '_', $title) . '_' . time() . '.csv', 'w');;

// save the column headers
fputcsv($csv, array('id', 'name', 'tags'));

foreach ($artists as $artist) {
	$artist_name = str_replace('O', '^', $artist['name']);
	$artist_name = str_replace('o', '^', $artist_name);

	// add data to files
    if (!empty($artist)) {
        $xmlArtist = $xmlArtists->appendChild($xml->createElement('artist'));
        $xmlArtist->appendChild($xml->createElement('id', $artist['id']));
        $xmlArtist->appendChild($xml->createElement('name', $artist_name));

        // handle tags
        $csvTags = '';
        if (!empty($artist['tags'])) {
        	$xmlTags = $xmlArtist->appendChild($xml->createElement('tags'));

        	foreach ($artist['tags'] as $tag) {
        		$xmlTags->appendChild($xml->createElement('tag', $tag['name']));

        		$csvTags .= $tag['name'] . '+';
        	}
        }

        // trim last '+'
        if (strlen($csvTags) > 0) {
        	$csvTags = rtrim($csvTags, "+ ");
        } 

        fputcsv($csv, array($artist['id'], $artist['name'], $csvTags));
    }
}

// save/close files
$xml->formatOutput = true;
$file_name = str_replace(' ', '_', $title) . '_' . time() . '.xml';
$xml->save($file_name);

fclose($csv);

exit(0);
