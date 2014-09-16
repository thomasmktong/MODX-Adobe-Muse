<?php
# Snippet to include template files from file system
# USAGE: [[includeTemplate? &tpl=`assets\_bespoke\tp_pg1.html`              // muse html file load as template 
#                           &component1=`assets\_bespoke\tp_accordion.html`  // muse html file load as component
#                           &placehold1=`u1391-2`                           // element id in !template! for the component to add in
#                           &repeatId1=`u1587`                              // element id in !component! which needed to repeat
#                           &repeatRef1=`1,3,4,7`                           // modx resources id for repeater to refer to
#                           &component2=`assets\_bespoke\tp_menu.html` ...]] // goes on

// check parameters integrity
if ( !isset($tpl) || $tpl== "" ) return "Missing Template file!";

// and count how many effective templates defined
$paramsMandate = array('component', 'placehold');
$templateCnt = -1;
for($i = 1; true; $i++)
    foreach ($paramsMandate as $param) {
        if(!isset(${$param . $i}) || ${$param . $i} == "") {
            $templateCnt = $i - 1;
            break;
        }
    }
    if($templateCnt != -1) break;
}

// load the whole template html to dom tree & xpath
$rel_path = substr($tpl, 0, strrpos($tpl, "/"));

$html = file_get_contents($tpl);
$dom = new DOMDocument;
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// load components
for($i = 1; $i <= $templateCnt; $i++) {

    $html_com = file_get_contents(${'component' . $i - 1});
    $dom_com = new DOMDocument;
    $dom_com->loadHTML($html_com);
    $xpath_com = new DOMXPath($dom_com);

    // merge body (everything inside 'page')
    // XPath Tester - http://videlibri.sourceforge.net/cgi-bin/xidelcgi
    $comNodes = $xpath_com->query("//div[@id='page']/*");
    $srcNodes = $xpath->query("//*[@id='" . ${'placehold' . $i} . "']");

    if(count($comNodes) > 0 && count($srcNodes) > 0) {

        // handle repeat
        if(!isset(${'repeatId' . $i}) || ${'repeatId' . $i} == ""  || !isset(${'repeatRef' . $i}) || ${'repeatRef' . $i} == "") {
            $repNodes = $xpath_com->query("//*[id='" . ${'repeatId' . $i} . "']", $comNodes->item(0));
            if(count($repNodes) > 0) {
                foreach (explode(',', ${'repeatRef' . $i}) as $repeatRef) {

                    // deep clone the repeating tag
                    $repNodeClone = $repNodes->item(0)->cloneNode(true);

                    // change element id
                    $repNodeClone->setAttribute('id', $repNodeClone->getAttribute('id') . $repeatRef);

                    // add it to the end of repeating tags
                    $repNodes->item(0)->parentNode->appendChild($repNoteClone);
                }
            }
        }

        // insert to template
        if($srcNodes->item(0)->nodeName != 'div' && $srcNodes->item(0)->nodeName != 'span') {
            // component replace placeholder (since it is not container)
            $srcNodes->item(0)->parentNode->replaceChild($comNodes->item(0), $srcNodes->item(0));
        } else {
            // component insert to placeholder
            $srcNodes->item(0)->appendChild($comNodes->item(0));
        }
    }

    // merge body (script) (scripts in head are not merged)
    // ... comapare blocks of code first, insert when missing
    // ... if code block type same, but content different, compare line by line, insert when missing
    $comNodes = $xpath_com->query("//body/script");
    $srcNodes = $xpath->query("//body/script");

    $lastSrcMatchedOrInserted = -1;
    for($j = 0; $j < count($comNodes); $j++) {

        // compare blocks of script and sort up unmatch
        $matched = false;
        for($k = 0; $k < count($srcNodes); $k++) {
            if(isSameScripts($comNodes->item($j), $srcNodes->item($k))) {
                $lastSrcMatchedOrInserted = max($lastSrcMatchedOrInserted, $k);
                $matched = true;
                break;
            }
        }

        if(!$matched) {
            if($comNodes->item($j)->hasAttribute('src')) {

                // if unmatch block is just referening file, add from component to template
                $insertPos = $lastSrcMatchedOrInserted + 1;
                if($insertPos >= count($srcNodes)) {
                    $srcNodes->item(0)->parentNode->appendChild($comNodes->item($j));
                } else {
                    $srcNodes->item(0)->parentNode->insertBefore($comNodes->item($j), $srcNodes->item($insertPos));
                }
            } else {

                // if unmatch block is the last in-page script block, compare line by line
            }
        }
    }

    // merge head (css)
    // ... compare link rel, insert when missing
    // ... if css file name same as component name page
}

// fix template referencing path
$srcNodes = $xpath->query('//@href|//@src|//@data-src');

foreach($srcNodes as $srcNode) {

    // sometimes we could use modx tags link in muse
    // muse automatically convert [[ to %5B%5B and ]] to %5D%5D, plus prefix http://
    if(endsWith(trim($srcNode->nodeValue), "%5D%5D")) {
        $tmpNodePos = strpos($srcNode->nodeValue, "//");
        if($tmpNodePos !== false) {
            $tmpNodeVal = substr($srcNode->nodeValue, $tmpNodePos + 2);

            if(startsWith($tmpNodeVal, "%5B%5B")) {
                $srcNode->nodeValue = str_replace($tmpNodeVal, array("%5B%5B", "%5D%5D"), array("[[", "]]"));
            }
        }
    }

	// absolute path
	$isColon = strrpos($srcNode->nodeValue, ":") !== false;
	$isDoubleSlash = startsWith($srcNode->nodeValue, "//");
    
	// relative path
	if(!$isColon && !$isDoubleSlash) {
		$srcNode->nodeValue = joinPaths($rel_path, $srcNode->nodeValue);
	}
}

// signify modx dynamic value containers
$ctnNodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//p');

foreach($ctnNodes as $ctnNode) {
    if(startsWith(trim($ctnNode->nodeValue), "[[") && 
        endsWith(trim($ctnNode->nodeValue), "]]")) {

        $cssClass = $ctnNode->getAttribute('class');
        $cssClass = (trim($cssClass) != '') ? trim($cssClass) . " modx" : "modx";
        $ctnNode->setAttribute('class', $cssClass); 
    }
}

// bespoke de bonifier add on scripts
$bonifierJS = '//bonifier.com.hk/shared/bonifier_muse2modx.js';
$bonifierCSS = '//bonifier.com.hk/shared/bonifier_muse2modx.css';

$headNode = $xpath->query('//head');

$cssNode = $dom->createElement('link');
$cssNode->setAttribute('rel', 'stylesheet');
$cssNode->setAttribute('type', 'text/css');
$cssNode->setAttribute('href', $bonifierCSS);

$headNode->item(0)->appendChild($cssNode);

$bodyNode = $xpath->query('//body');

$jsNode = $dom->createElement('script');
$jsNode->setAttribute('type', 'text/javascript');
$jsNode->setAttribute('src', $bonifierJS);

$bodyNode->item(0)->appendChild($jsNode);

ob_start();
print $dom->saveHTML();
return ob_get_clean();

function startsWith($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function joinPaths() {
    $args = func_get_args();
    $paths = array();
    foreach ($args as $arg) {
        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
    $paths = array_filter($paths);
    return join('/', $paths);
}

function isSameLines($str1, $str2) {
    return trim($str1) === trim($str2);
}

function isSameScripts($node1, $node2) {
    if($node1->hasAttribute('src')) {
        if($node2->hasAttribute('src')) {
            return isSameLines($node1->getAttribute('src'), $node2->getAttribute('src'))
        } else {
            return false;
        }
    } else {
        return isSameLines($node1->nodeValue, $node2->nodeValue)
    }
}