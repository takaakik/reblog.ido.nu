<?php
require_once 'mobileicon.php';
require_once('Net/UserAgent/Mobile.php'); 

function to_mobile_url($u) {
    # shortcut key.
    if ( preg_match( '%^(#|/reblog/)%', $u) ) {
        return $u;
    }
    if ( preg_match( '|^http://([\w\-]+).tumblr.com((/(.{6}).+)?)|', $u, $m) ) {
        if ( $m[2] == '' or ($m[1] != 'mobile' and $m[1] != 'data' ) ) {
            if ( $m[2] == '' ) 
                return 'http://' . $m[1] . '.' . $_SERVER['SCRIPT_URI'] . $m[2];
            else {
                $path = preg_replace( '/(\/post\/\d+).*/', '$1', $m[2]);
                return 'http://' . $m[1] . '.tumblr.com/mobile' . $path;
            }
        }
    } else {
        $u = '/redirect/' . $u;
    }
    return $u;
}

function image_replace_callback($m) {
	if ( preg_match( '/\/$/', $m[1] ) ) {
		return "<img" . $m[1] . ">";
	} else {
		return "<img" . $m[1] . "/>" ;
	}
}


class Dashboard {
	function __construct($content) {
		$html = $this->wash($content);
		
		$dom = new DOMDocument();
		$dom->loadXML( $html );

		$this->dom = $dom;
        
        $this->agent = Net_UserAgent_Mobile::singleton();
	}

	function parse() {
		$x = new DOMXPath($this->dom);

		$paragraphes = $x->evaluate('//*[@id="posts"]/li[ @id and not( contains(@class,"with_avatar") ) and not(@class="post") ]', $x->document);

		// prevent to leak sessionkey via referer.
		$anchors = $x->evaluate('//*[@id="posts"]/li//a', $x->document);
		foreach ( $anchors as $k => $v ) {
			$u = $v->getAttribute('href');
			if ( preg_match('%^http://%i', $u ) ) {
				$u = to_mobile_url($u);
				$v->setAttribute('href', $u);
				$u = $v->getAttribute('href');
			}
		}

		$images = $x->evaluate('//*[@id="posts"]/li//img[ not(ancestor::li[contains(@class,"photo")]) ]', $x->document);
		foreach ( $images as $k => $v ) {
			$u = $v->getAttribute('src');

			if ( ! preg_match('%^http:\/\/(media|data|assets)\.tumblr\.com%i', (string)$u ) ) {
				$v->setAttribute('xOriginalsrc', $u);
				$v->setAttribute('src', '');
			}
		}

		$posts = array();
		foreach ($paragraphes as $k=> $paragraph) {
			$posts[] = $p = new Post($x, $paragraph);
			$p->getPostInfo();
		}
		$this->posts = $posts;
	}
    
	function html_header() {
		global $sessionkey;
		$me = $this->me;
        $agent = $this->agent;
        
        print '<html>';
        print '<head>';
        print '<meta http-equiv="Content-Type" content="text/html; charset=shift_jis"/>';
        print "<title>$me dashboard</title>";
        
		if ($agent->isDoCoMo()) {
        } else {
            print '<style>';
            print 'div {';
            print 'padding-top: 2px;';
            print 'padding-bottom: 2px;';
            print '}';
            print 'div.odd {';
            print 'background-color: rgb(245, 245, 245);';
            print '}';
            print '</style>';
        }
        
        print '</head>';
        print '<body>';
        print "<h1>$me dashboard</h1>";

		$this->page = $page = getPage();
		if ( $page == 1 ) {
				print "plz bookmark this page.";
		} else {
			$nextpage = $this->page + 1;

			$u = $_SERVER['SCRIPT_URI'];
			$u .= "?page=$nextpage";
			print "page $page ";
			print "<a href=\"$u\" rel=\"next\">older</a>";
		}

	}
    
	function html_footer($last_postid) {
		global $sessionkey;
        
        $agent = $this->agent;

		$nextpage = $this->page + 1;

		$u = $_SERVER['SCRIPT_URI'];
		$u .= "?page=$nextpage&postid=$last_postid";
		$u .= "&.rand=" . rand();

		$now = time();

		if ($agent->isDoCoMo()) {
            print "<hr />";
        } else {
            print "<div style=\"clear: both\">";
        }
        
        $k = '*';
		print "[$k]<a href=\"/status/$sessionkey?at=$now\" accesskey=\"$k\" directkey=\"$k\">reblog status</a>";
		print "<br />";
		$k = '#';
		print "[$k]<a href=\"$u\" rel=\"next\" accesskey=\"$k\" directkey=\"$k\">older</a><br />";
        
        if ($agent->isDoCoMo()) {
        } else {
            print "</div>";
        }
		
        print $_SERVER['HTTP_HOST'];

		print "</body></html>";
	}

	function render () {
	 	$this->html_header();
         
        $agent = $this->agent;

        if ($agent->isDoCoMo()) {
        } else {
            print "<div class=\"autopagerize_page_element\">";
        }
        
        // paging a page
        if ($agent->isDoCoMo()) {
            $page = getPage();
            if (($page % 2) == 1) {
                $posts = array_slice($this->posts, 0, 5);
            } else {
                $posts = array_slice($this->posts, 5);
            }
        } else {
            $posts = $this->posts;
        } 
        
		foreach ( $posts as $k => $p ) {
			$classname = ($k % 2) ? 'odd': 'even';
			print "<a name=p$k id=p$k />";

			if ( @$_REQUEST["reblog"] and "p$k" == @$_REQUEST["anchor"] ) { 

                if ($agent->isDoCoMo()) {
                    print 'reblogging...<br />';
                } else {
                    print "<div>reblogging...</div>";
                }

			}
            
            if ($agent->isDoCoMo()) {
                print '<hr />';
            } else {
                print "<div class=\"$classname\" style=\"clear: both\" >\n";
            }
            
			$u = $p->userid;
			$link  = $p->permalink;
			$safeLink  = $p->safePermalink;
			$type  = $p->postType;

			
			print "[<a href=#p$k accesskey=$k directkey=$k />";
			$icon = get_number_icon($k);
            
		 	if ($agent->isEZWeb()) {
				$icon = preg_replace('/\D/', '', $icon);
				print "<img localsrc=$icon />";
			} else {
				print "$k";
			}
            
			print "</a>]";

			$post_id = $p->id;
			$page = $this->page;

			global $sessionkey;

			if ( $token =$p->reblogToken ) {
				print "<a href=\"/reblog/$sessionkey?permalink=$link&postid=$post_id&token=$token&anchor=p$k&page=$page\">reblog</a>";
			}
			print '<a href="http://' . $u . $_SERVER['HTTP_HOST'] . '">' . $u . '</a>';

			print "<br/>";

			$post_content = nument2chr(mb_convert_encoding($p->post_content, 'SHIFT_JIS', 'UTF-8'));
			$post_title = nument2chr(mb_convert_encoding($p->post_title, 'SHIFT_JIS', 'UTF-8'));

			$content = '';

			switch($type) {
				case 'photo':
					$img = $p->image;
					$qvga = preg_replace('/_100.jpg/', '_250.jpg', $img);
					
                    if ($agent->isDoCoMo()) {
                        print "<a href=\"/mobile_image.php?img=$qvga\" ><img src=\"/mobile_image.php?tn=1&img=$img\" width=80/></a>";
                        $content .= $post_content;
                    } else {
                        print "<a href=$qvga style=\"float:left;\"><img src=\"$img\" width=100/></a>";
                        $content .= "<div style=\"float:left;\">";
                        $content .= $post_content;
                        $content .= "</div>";
                    }
                    
					break;
				case 'quote':
					$content .= $post_content;
					$content .= $post_title;
					break;
				case 'link':
					$l = $p->linkurl;
					$t = $post_title;
					$content .= "<a href=\"$l\">$t</a><br />";
					$content .= $post_conten;
					break;
				case 'regular':
					$content .= $post_content;
					break;
				case 'video':
					$content .= $post_content;
					break;
				case 'audio':
					$content .= $post_content;
					break;
				default:
					$content .= "unknown type $type";
			}
			
            print $content;
            
            if ($agent->isDoCoMo()) {
            } else {
                print "\n</div>\n";
            }

			$last_postid = $post_id;
		}
        
        if ($agent->isDoCoMo()) {
        } else {
            print "</div>\n";
        }

		$n =  count($this->posts);
		$p = $this->posts[$n-1];
		$last_postid = $p->id;
	 	$this->html_footer($last_postid);
	}

	function wash($content) {
		$content = preg_replace('|<b></b>|', '', $content);
		$content = preg_replace('/\s*\n\s*/', "\n", $content);
		$content = preg_replace('/\b(\w+=")\n\s*/', '$1', $content);
		$content = preg_replace('/\s*\n\s*/', "\n", $content);
		$content = preg_replace('/\s*\n\s*>/', '>', $content);

		$parser = new HTML_Safe();
		$parser->attributes = array();
		$parser->deleteTags[] = 'noscript';
		$parser->deleteTagsContent[] = 'noscript';
		$result = $parser->parse($content);
		$result = $parser->getXHTML();

		$content = '<html><body>' . $result . '</body></html>';
        
        // remove reblog lineages.
        $content = preg_replace( '/<p><a href=".+?">\w+<\/a>:<\/p>/', '', $content );
		$content = $this->removeEntities($content);

		return $content;
	}

    function removeEntities($html) {
        $html = preg_replace('/&mdash;/', ' ', $html);
        $html = preg_replace('/\&nbsp;/', '', $html);
        $html = preg_replace('/&copy;/', '(c)', $html);
        $html = preg_replace('/&(?!amp;)/', '&amp;', $html);
        
        // is this a PHP bug? replaces everything with ''. sucks.
        $html = preg_replace_callback('/<img(.+?)>/ms', "image_replace_callback", $html);
        // $html = preg_replace_callback('/<img(.+?)>/', "image_replace_callback", $html);
        
        return $html;
    }
}

class Post {
	public $id = null;
	public $post_cotent = null;
	public $post_title = null;
	public $linkurl = null;
	public $reblogToken = null;

	function __construct($xpath, $context) {
		$this->x = $xpath;
		$this->context = $context;
	}
    function x1 ($expression, $context = null) {
		$r = $this->x($expression, $context);
		return ( $r ) ? $r[0]: $r;
    }
    function x ($expression, $context = null) {
        if ( ! $context ) $context = $this->context;
        $nodes = $this->x->evaluate($expression, $context);
		$res = array();
        foreach ($nodes as $k => $v ) {
        	$res[] = $v;
        }
        return $res;
    }

	function quote() {
		$contents = $this->x('.//div[@class="post_info"]/following-sibling::node()[following-sibling::div[not(contains(@style,"none"))]]');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= dump_children($node);
		}

        $html = preg_replace('/^\s*“/', '', $html);
		$html = preg_replace('/”\s*$/', '', $html);
		$html = preg_replace('/　\n?$/', '', $html);
		$this->post_content = $html;

		$node = $this->x1( './/div[@class="post_container"]/div[position() = last() -1]' );
		$title = preg_replace( '//', '', dump_children($node) );

		$this->post_title = $title;

	}
	function photo() {
		$contents = $this->x('.//div[@class="caption"]');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= as_string($node);
		}
		$this->post_content = $html;

		$i = $this->x1('.//img[@class="image"]/@src');
		if ( $i ) {
			$i = $i->nodeValue;
			$this->image = preg_replace('/_500\.(\w+)/', '_100.$1', $i);
		}
	}
	function audio() {
		$contents = $this->x('.//div[@class="post_body"]');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= dump_children($node);
		}
		$this->post_content = $html;
	}
	function regular() {
		$contents = $this->x('.//div[@class="post_info"]/following-sibling::node()[following-sibling::div[contains(@style,"none")]]');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= dump_children($node);
		}
		$this->post_content = $html;

		#$contents = $this->x('.//div[@class="username"]/following-sibling::p[ last() ]');

	}
	function video() {
		$contents = $this->x('.//div[contains(@class, "post_body")]/node()');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= dump_children($node);
		}
		$this->post_content = $html;

	}
	function link() {
		$link = $this->x1('.//div[@class="post_title"]/a');
		if ( !$link ) 
			return;

		$linkurl = $link->getAttribute('href');
		$title = $link->textContent;

		$this->linkurl = $linkurl;
		$this->post_title = $title;
		
		#$contents = $this->x('.//div[@class="username"]/following-sibling::div[1]');
		$contents = $this->x('.//div[@class="post_info"]/following-sibling::div[not(contains(@style,"none"))]');
		$html = '';
		foreach ( $contents as $k => $node ) {
			$html .= dump_children($node);
		}
		$this->post_content = $html;
	}

	function getPostID() {
		$img = $this->x1( './/img[contains(@id, "permalink_")]'  );
		if ( !$img ) {
			return null;
		}
		
		$a = $img->parentNode;

		$id = $img->getAttribute('id');
		$this->permalink = $a->getAttribute('href');
		$this->safePermalink = preg_replace( '/tumblr.com\/(?!mobile\/)/', 'tumblr.com/mobile/', $this->permalink);


		if ( ! preg_match('/\d+$/', $id , $m )  )
			return null;
		$this->id = $m[0];
		
	}

	function getPostInfo() {
		$this->getPostID();
		$classname = $this->context->getAttribute("class");

		$types = array( 'photo', 'quote', 'regular', 'video', 'link', 'chat', 'audio' );

		foreach( $types as $k => $v ) {
			if ( preg_match('/' . $v . '/', $classname) ) { 
				$this->postType = $v;
				break;
			}
		}

		if ( ! $this->id )
			return 0;

		if ( $this->postType ) {
			$method = $this->postType;

			$this->$method();

			$this->post_content = trim ($this->post_content);

			#preg_match( '/http:\/\/([^.]+)\./', $this->permalink, $m);
			#$this->userid = $m[1];
			$username = $this->x1('.//span[@class="username"]/a[1]/text() ' );
			$this->userid = $username->nodeValue;

			$reblogLink = $this->x1('.//div[@class="post_controls"]/a[not(@id)]/@href' );

			if ( preg_match( '/\/reblog\/\d+\/(\w+)\?/', $reblogLink->nodeValue, $m ) ) {
				$this->reblogToken = $m[1];
			}
		}
		return 0;
	}

}

function dump_children($n) {
	$html = '';
	if ( $n->childNodes ) {
		foreach ( $n->childNodes as $child ) {
			$html .= as_string($child);
		}
		return $html;
	} else {
		return as_string($n);
	}
}
// stringifies childNodes.
function as_string($n) {
	if ( is_a($n, 'DOMText') ) {
		return $n->textContent;
	} else {
		if ( is_array($n) ) {
			return;
		}
		$v = simplexml_import_dom($n);
		return $v->asXML();
		#return $n->ownerDocument->saveXML($n);
	}
}
function getPage() {
	$page = (int)( @$_REQUEST['page'] );
	if ($page < 1 ) {
		$page = 1;
	}
	return $page;
}

function nument2chr($string) {
    // 文字コードチェック
    $encoding = strtolower(mb_detect_encoding($string));
    if (!preg_match("/^utf/", $encoding) and $encoding != 'ascii') {
        return '';
    }
    // 16 進数の文字参照(らしき表記)が含まれているか
    $excluded_hex = $string;
    if (preg_match("/&#[xX][0-9a-zA-Z]{2,8};/", $string)) {
        // 16 進数表現は 10 進数に変換
        $excluded_hex = preg_replace("/&#[xX]([0-9a-zA-Z]{2,8});/e", "'&#'.hexdec('$1').';'", $string);
    }
    return mb_decode_numericentity($excluded_hex, array(0x0, 0x10000, 0, 0xfffff), "Shift-jis");
}

?>
