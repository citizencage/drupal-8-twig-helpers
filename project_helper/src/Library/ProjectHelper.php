<?php

/**
 * @file
 * Contains \Drupal\project_helper\Library\ProjectHelper
 * Purpose: provides utility functions for Drupal 8 projects
 * @author	Rusty Cage - Healthgrades
 * Web: http://healthgrades.com
 * Email: rcage@healthgrades.com
 */

namespace Drupal\project_helper\Library;
use Drupal\taxonomy\Entity\Term;

class ProjectHelper {

    protected $session;

    /**
     * Constructor.
     */
    public function __construct() {

    }

    /**
     * @param $needle
     * @param $haystack
     * @param bool $strict
     * @return bool
     */
    public function in_array_r($needle, $haystack, $strict = false) {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
                return true;
            }
        }
        return false;
    }



    /**
     * @method customizeTermAlias
     * @params tid(numeric)
     * @params replacement(string)
     * Purpose: substitute generic taxonomy term alias with a custom url alias (for instance, contextual); preserves the term's slug
     */
    public static function customizeTermAlias($tid, $replacement) {
        $aliasManager = \Drupal::service('path.alias_manager');
        $alias = $aliasManager->getAliasByPath('/taxonomy/term/'.$tid);
        if($alias != '') {
            $pattern = '/^.*\/\s*/';
            $alias = preg_replace($pattern, $replacement, $alias);
        }
        return $alias;
    }

    /**
     * @param int $len
     * @return bool|string
     */
    public function randHash($len=32) {
        return substr(md5(openssl_random_pseudo_bytes(20)),-$len);
    }

    /**
     * @param $alias
     * @return array|\Drupal\Core\Entity\EntityInterface|Term|null
     */
    public static function getTaxonomyTermFromAlias($alias) {
        $term = array();
        // get the system path from alias param
        $alias = self::removeInvalidChars($alias);
        $system_path = \Drupal::service('path.alias_manager')->getPathByAlias($alias);
        if(isset($system_path) && $system_path != '') {
            // fetch term from term id contained in system path
            $term = Term::load(self::removeNonNumeric($system_path));
        }

        return $term;
    }

    /**
     * @method getTaxonomyAliasFromTerm
     * @params tid(numeric)
     * Purpose: retrieves the term url alias
     */
    public static function getTaxonomyAliasFromTerm($tid) {
        $aliasManager = \Drupal::service('path.alias_manager');
        $alias = $aliasManager->getAliasByPath('/taxonomy/term/'.$tid);
        return $alias;
    }

    /**
     * @param $array
     * @return array
     */
    public static function sanitizeArrayVals($array) {
        $new_array = array();
        if (!empty( $array ) && is_array( $array ) ) {
            foreach( $array as $key => $value ) {
                $new_array[] = filter_var( $value, FILTER_SANITIZE_STRING );
            }
        }
        return  $new_array;
    }

    /**
     * @param $str
     * @return string|string[]|null
     */
    public static function removeInvalidChars($str) {
        return preg_replace('/[^-a-zA-Z0-9_:&\/|, ]/', '', $str);
    }

    /**
     * @param $str
     * @return string|string[]|null
     */
    public static function removeNonNumeric($str) {
        return preg_replace('/\D/', '', $str);
    }



  /*
 * Truncate text
 * @param string  $text	String to truncate.
 * @param integer $length Length of returned string, including ellipsis.
 * @param string  $ending Ending to be appended to the trimmed string.
 * @param boolean $exact If false, $text will not be cut mid-word
 * @param boolean $considerHtml If true, HTML tags would be handled correctly
 * @return string Trimmed string.
 */
    public function htmlTruncate($text, $length = 100, $ending = '...', $exact = true, $considerHtml = true) {
        if ($considerHtml) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }

            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);

            $total_length = strlen($ending);
            $open_tags = array();
            $truncate = '';

            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
                    if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                        // do nothing
                        // if tag is a closing tag (f.e. </b>)
                    } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                        // if tag is an opening tag (f.e. <b>)
                    } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }

                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                if ($total_length+$content_length > $length) {
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1]+1-$entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    // maximum lenght is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }

                // if the maximum length is reached, get off the loop
                if($total_length >= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = substr($text, 0, $length - strlen($ending));
            }
        }

        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (isset($spacepos)) {
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }

        // add the defined ending to the text
        $truncate .= $ending;

        if($considerHtml) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }

        return $truncate;

    }

    /**
     * @param null $name
     * @param null $vid
     * @return int|string|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function getTidByName($name = NULL, $vid = NULL) {
        $properties = [];
        if (!empty($name)) {
            $properties['name'] = $name;
        }
        if (!empty($vid)) {
            $properties['vid'] = $vid;
        }
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
        $term = reset($terms);
        return !empty($term) ? $term->id() : 0;
    }


}

