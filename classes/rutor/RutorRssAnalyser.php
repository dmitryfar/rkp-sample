<?
namespace rutor;

class RutorRssAnalyser {
	public $newDate;
	public $rssItems = array();
	public $parsedRssItems = array();

	function RutorRssAnalyser() {
		//$categoryId;
		$args = func_get_args();
		if (count($args) > 0) {
			$categoryId = $args[0];
		}

		// TODO: last date for categoryId?

		$lastDate = Rss::getRssDate($categoryId);
		//$lastDate2 = RutorRss::getLastDate($categoryId);
		echo "lastDate: ".$lastDate->format('Y-m-d H:i:s')."<br />";
		//return;
		$this->rssItems = $this->getRssItems($categoryId, $lastDate);

		$i = 0;
		foreach($this->rssItems as $rssItem) {
			// skip other
			$inList = in_array($rssItem->rutorCategory->name, array('kino', 'nashe_kino', 'nauchno_popularnoe', 'seriali', 'tv', 'multiki', 'anime'));
			// dump($rssItem);
			if ($rssItem->rutorCategory != null && $inList) {
				dump($rssItem);
				// echo "rssItem in list<br />";
				$rutorParser = $this->analyzeTorrent($rssItem->torrentId);
				$rutor = Rutor::getByTorrentId($rssItem->torrentId);
				if ($rutor) {
					try {
						//$rutor->createDate = $rssItem->pubDate;
						$rutor->updateCreateDate($rssItem->pubDate->format('Y-m-d H:i:s'));
						//echo "createDate updated<br/ >";
					} catch(Exception $e) {
						echo "Error on update createDate<br/ >";
					}
				}
				$this->parsedRssItems[] = $rssItem;

				/*
				//echo "innerTorrentIds: ". $rutorParser->torrentIds. "<br />";
				if ($rutorParser && count($rutorParser->torrentIds) > 0) {
					foreach($rutorParser->torrentIds as $innerTorrentId) {
						echo "parse innerTorrentId $innerTorrentId<br />";
						$this->analyzeTorrent($innerTorrentId);
					}
				}
				*/
			}

			// if ($rssItem->pubDate > $this->newDate) {
			// 	$this->newDate = $rssItem->pubDate;
			// }
			if ($rssItem->rutorCategory != null) {
				$rssCategoryDate = Rss::getRssDate($rssItem->rutorCategory->id);
				if ($rssItem->pubDate > $rssCategoryDate) {
					echo "set new date for category ".$rssItem->rutorCategory->id."<br />";
					Rss::updateRssDate($rssItem->pubDate->getTimestamp(), $rssItem->rutorCategory->id);
				} else {
					echo "is old date for category ".$rssItem->rutorCategory->id."<br />";
				}
			}

			//$i++;
			if ($i>3) {
				break;
			}
		}

		//echo "total rss items: ".count($this->rssItems).", parsedRssItems: ".count($this->parsedRssItems)."<br />";
		//Rss::updateRssDate($this->newDate->getTimestamp(), $categoryId);
		//echo "newDate: ".$this->newDate->format('Y-m-d H:i:s')."<br />";
	}

	public static function analyzeTorrent($torrentId) {
		// parse rutor page
		$rutorNameParser = null;
		$kinopoiskParser  = null;
		$kinopoiskData = null;
		$dbLink = null;

		$rutorParser = new RutorParser($torrentId);
		if ($rutorParser->error) {
			echo "ERROR: can not parse torrent $torrentId<br />\n";
			echo "kpBannerUrl: {$rutorParser->kpBannerUrl}<br />\n";
			echo "torrentName: {$rutorParser->torrentName}<br />\n";
			Rutor::delete($torrentId);
			return;
		}
		$kpId = $rutorParser->kpMovieId;
		$imdbId = $rutorParser->imdbMovieId;

		// check Rutor by torrentId
		echo "check Rutor for torrentId ".$torrentId."...<br />";
		$rutor = Rutor::getByTorrentId($torrentId);
		if ($rutor != null) {
			echo "exists<br />";
            $rutor->hash = $rutorParser->hash;
            $rutor = $rutor->update();
		} else {
			echo "is new <br />";
			// parsed name
			$rutorNameParser = new RutorNameParser($rutorParser->torrentName);

			// add Rutor only if kp or imdb banner exists
			if ($kpId != null) {
				// create Rutor
				echo "create<br />";
				$rutor = Rutor::create(
					$torrentId,
					$rutorParser->hash,
					$rutorNameParser->rutorName,
					$rutorNameParser->rusName,
					$rutorNameParser->originalName,
					$rutorNameParser->releaser,
					$rutorNameParser->propertiesStr,
					$rutorNameParser->translatesStr,
					$rutorNameParser->year,
					$rutorNameParser->quality,
					$rutorNameParser->episodes,

					$rutorParser->details["categoryId"],
					$rutorParser->details["fileSize"]
				);
				//$rutor = $rutor->update();
			}
		}

        // db link

        echo "check DBLink for torrentId ".$torrentId." and kpId ".$kpId."...<br />";
        $dbLink = DBLink::getByTorrentId($torrentId);

        if ($dbLink == null) {
            echo "create<br />";
            $dbLink = DBLink::create($torrentId, $kpId, $imdbId);
            //$dbLink = $dbLink->update();
        } else if ($dbLink->kpId != $kpId || $dbLink->imdbId != $imdbId) {
            echo "exists, update DBLink for torrent $torrentId kpId from {$dbLink->kpId} to $kpId or imdbId from {$dbLink->imdbId} to $imdbId<br />";
            $dbLink->kpId = $kpId;
            $dbLink->imdbId = $imdbId;
            $dbLink = $dbLink->update();
        }

        // kp

        if ($kpId != null) {
			echo "check KinopoiskData for kpId ".$kpId."...<br />";
			$kinopoiskData = KinopoiskData::getByKpId($kpId);
			if ($kinopoiskData == null) {
				echo "create<br />";
				$kinopoiskParser = new KinopoiskParser($kpId);

				// $kpId, $title, $originalTitle, $year, $duration, $ratingValue, $ratingCount, $createDate
				$kinopoiskData = KinopoiskData::create($kinopoiskParser->movieId, $kinopoiskParser->title,
					$kinopoiskParser->originalTitle, $kinopoiskParser->year, $kinopoiskParser->duration,
					$kinopoiskParser->rating["kpRatingValue"], $kinopoiskParser->rating["kpRatingCount"]);
				//$kinopoiskData = $kinopoiskData->update();
			} else {
				echo "exists, update kp rating<br />";
				// update rating
				$rating = KinopoiskParser::parseKpRating($kpId);
				if ($rating) {
					$kinopoiskData = KinopoiskData::updateRating($kpId, $rating["kpRatingValue"], $rating["kpRatingCount"]);
				}
			}
		} else {
			echo "kpId not found in torrent page ".$torrentId."<br />";
			Rutor::delete($torrentId);
		}

		echo "-----------------------------<br />";


		if($rutorParser != null) {
			$rutorParser->dump();
		}
		if ($rutor) {
			$rutor->dump();
		}
		if ($rutorNameParser != null) {
			$rutorNameParser->dump();
		}
		if ($kinopoiskParser != null) {
			$kinopoiskParser->dump();
		}
		if ($kinopoiskData != null) {
			$kinopoiskData->dump();
		}
		if ($dbLink != null) {
			$dbLink->dump();
		} else {
			echo "dbLink is null<br />";
		}

		echo "=============================<br />";

		return $rutorParser;
	}

	function getRssItems($categoryId, $lastDate) {
		//$rssUrl = "http://alt.rutor.org/rss.php?cat=".$categoryId;
		//$rssUrl = "http://alt.rutor.org/rss.php";
		//$rssUrl = "http://localhost/rkp/rss/rss.xml"; /* $rssUrl = "http://alt.rutor.org/rss.php?"; */
		//$rssUrl = "http://localhost/rkp/rss/rss-".$categoryId.".xml";
		if ($categoryId != null) {
			$rssUrl = Properties::get("rutor.rss.category.url", $categoryId);
		} else {
			$rssUrl = Properties::get("rutor.rss.url");
		}

		$rss = simplexml_load_file($rssUrl);

		$torrents = array();
		if($rss) {
			$items = $rss->channel->item;
			$i = 0;
			// echo "<table border='1'><tr><th>torrentId</th><th>pubDate</th><th>categoryRu</th></tr>";
			foreach($items as $item) {
				$rssItem = new RssItem($item);
				// check date
				if ($rssItem->pubDate > $lastDate) {
					$torrents[] = $rssItem;

					echo "torrentId: ".$rssItem->torrentId.
						", pubDate: ".$rssItem->pubDate->format('Y-m-d H:i:s').
						", lastDate: ".$lastDate->format('Y-m-d H:i:s').
						", categoryId: ".$rssItem->categoryRu."<br />";
					// echo "<tr>";
					// echo "<td>".$rssItem->torrentId."</td><td>".$rssItem->pubDate->format('Y-m-d H:i:s')."</td><td>".$rssItem->categoryRu."</td>";
					// //echo "<td>".(($inList)? "" : "skipped")."</td>";
					// echo "</tr>";
				}

				/*
				$rutor = new Rutor($torrentId);
				echo "<pre>"; print_r($rutor->toArray()); echo "</pre>";

				//echo json_encode($rutor->toArray());
				*/
			}
			// echo "</table>";
		}

		return array_reverse($torrents);
	}

	function dump() {
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}
}
?>