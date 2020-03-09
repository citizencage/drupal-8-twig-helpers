<?php

namespace Drupal\project_helper\TwigExtension;
use Drupal\taxonomy\Entity\Term;

class ProjectHelperExtension extends \Twig_Extension {

    /**
     * {@inheritdoc}
     * This function must return the name of the extension. It must be unique.
     */
    public function getName() {
        return 'ProjectHelper.twig_extension';
    }

    /**
     * Generates a list of all Twig filters that this extension defines.
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('formatPhone', array($this, 'formatPhone'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('commaSeparatedTerms', array($this, 'commaSeparatedTerms'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('videoEmbed', array($this, 'videoEmbed'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('videoEmbedWithPoster', array($this, 'videoEmbedWithPoster'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('truncateText', array($this, 'truncateText'), array('is_safe' => array('html'))),
        ];
    }

    public function getFunctions()
    {
        return [
          new \Twig_SimpleFunction('generateTaxonomyTerms', array($this, 'generateTaxonomyTerms'), array('is_safe' => array('html'))),
          new \Twig_SimpleFunction('generateMonthYearNodeFilter', array($this, 'generateMonthYearNodeFilter'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('getTaxonomyTermName', array($this, 'getTaxonomyTermName'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('getTaxonomyTermDescription', array($this, 'getTaxonomyTermDescription'), array('is_safe' => array('html'))),
        ];
    }

    /**
     * @param $txt - string
     * @param $length - integer
     * @return bool|string
     */
    public static function truncateText($txt, $length) {
        $helper = new \Drupal\project_helper\Library\ProjectHelper();
        return $helper->htmlTruncate($txt, $length);
    }



    /**
     * @param $video - video embed string
     * @param $source - video source (youtube / vimeo)
     * @return string
     */
    public static function videoEmbed($video, $source) {
        $str = '';
        $video_provider = ($source == 'youtube') ? $video.'?autoplay=0&start=0&rel=0&modestbranding=1' : $video;
        $str .= '<div class="embed-container">';
        $str .= '<iframe src='.$video_provider.' frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
        $str .= '</div><!-- embed-container -->';
        return $str;
    }

    /**
     * @param $video
     * @param $poster
     * @param $source
     * @return string
     */
    public static function videoEmbedWithPoster($video, $poster, $source) {
        $helper = new \Drupal\project_helper\Library\ProjectHelper();
        $str = '';
        if($source == '') {
            if (stripos(strtolower($video), 'youtube') !== false) {
                $source = 'youtube';
            }
        }
        if($source == 'youtube') {
            if (stripos(strtolower($video), 'watch') !== false) {
                list(, $hash) = explode('?v=', $video);
                $video = 'https://www.youtube.com/embed/'.$hash;
            }
        } else {
            $id = substr($video, strrpos($video, '/') + 1);
            $video = 'https://player.vimeo.com/video/'.$id;
        }
        $hash = $helper->randHash(20);
        $video_provider = ($source == 'youtube') ? $video.'?autoplay=1&start=0&rel=0&modestbranding=1' : $video.'?&autoplay=1&loop=1';
        $str .= '<div class="videoWrapper videoWrapper169 js-videoWrapper">';
        $str .= '<iframe class="videoIframe js-videoIframe" src="" data-src='.$video_provider.' frameborder="0" allowFullScreen allow="autoplay; fullscreen"></iframe>';
        $str .= '<button id="'.$hash.'" class="videoPoster js-videoPoster" style="background-image:url('.$poster.');">Play video</button>';
        $str .= '</div><!-- videoWrapper -->';
        return $str;
    }

    /**
     * @param $txt
     * @return string|string[]|null
     */
    public static function formatPhone($txt) {
        // Allow only Digits, remove all other characters.
        $number = preg_replace("/[^\d]/","",$txt);
        // get number length.
        $length = strlen($number);
        // if number = 10, reformat the phone as (xxx) xxx-xxxx
        if($length == 10) {
            $number = preg_replace("/^1?(\d{3})(\d{3})(\d{4})$/", "($1) $2-$3", $number);
        }
        return $number;
    }


    /**
     * @method commaSeparatedTerms - creates a simple comma separated list of terms, with or without surrounding HTML markup
     * @param $taxonomy_entity - taxonomy reference (will contain target ids)
     * @param $tag - name of tag to surround the terms (i.e. "span")
     * @param $class - name of class (i.e. "my-class-name")
     * @return string
     */
    public static function commaSeparatedTerms($taxonomy_entity, $tag, $class) {
        $terms = array();
        $str = '';
        if(sizeof($taxonomy_entity) > 0) {
            foreach ($taxonomy_entity as $k => $reference) {
                $item = Term::load($reference->target_id);
                array_push($terms, $item->name->value);
            }
            if(isset($tag) && trim($tag) != '') {
              $i = 0;
              $len = count($terms);
              $class_str = ( isset($class) && trim($class) != '' ) ? ' class="'.$class.'"' : '';
              foreach($terms as $val) {
                  if ($i == $len - 1) {
                      // last iteration
                      $str .= '<'.$tag.$class_str.'>'.$val.'</'.$tag.'>';
                  } else {
                      $str .= '<'.$tag.$class_str.'>'.$val.', </'.$tag.'>';
                  }
                  $i++;
              }
            } else {
                $str = implode(', ', $terms);
            }

        }
        return $str;
    }

    /**
     * @method generateTaxonomyTerms
     * @params vocab(string) - machine name of the taxonomy vocabulary
     * @params listType (string) - 'options' / 'list'
     * Purpose: generates a simple list of taxonomy terms; eliminates need of having to generate complex view or block to output basic list markup for terms
     */
    public static function generateTaxonomyTerms($vocab, $listType) {
        $markup = '';
        $type = (isset($listType)) ? $listType : 'options';
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocab);
        if(sizeof($terms)>0) {
            foreach ($terms as $term) {
                if($type == 'options') {
                    $markup .= '<option value="' .$term->tid. '">' .$term->name. '</option>';
                } else {
                    $markup .= '<li>'.$term->name.'</li>';
                }
            }
        }
        return $markup;
    }

    /**
     * @param $nodeType
     * @param $listType
     * @return string
     */
    public static function generateMonthYearNodeFilter($nodeType, $listType) {
        $helper = new \Drupal\project_helper\Library\ProjectHelper();
        $markup = '';
        $dateArray = array();
        $type = (isset($listType)) ? $listType : 'options';
        $nids = \Drupal::entityQuery('node')
            ->condition('status', 1)
            ->condition('type', $nodeType)
            ->sort('created', 'ASC')
            ->execute();
        if(sizeof($nids) > 0) {
            $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
            foreach($nodes as $node) {
                $timestamp = $node->created->value;
                $dl = \Drupal::service('date.formatter')->format($timestamp, 'custom', 'F Y');
                $dv = \Drupal::service('date.formatter')->format($timestamp, 'custom', 'Y-m');

                if(!$helper->in_array_r($dl, $dateArray))
                    array_push($dateArray, array('val' => $dv, 'label' => $dl));
            }
            $dateArray = array_reverse($dateArray, true);
            foreach($dateArray as $item) {
                if($type == 'options') {
                    $markup .= '<option value="' .$item['val']. '">' .$item['label']. '</option>';
                } else {
                    $markup .= '<li>'.$item['label'].'</li>';
                }

            }
        }
        return $markup;
    }

    /**
     * @param $tid
     * @return string
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public static function getTaxonomyTermName($tid) {
        $name = '';
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
        if(sizeof($term)>0) {
            $name = $term->getName();
        }
        return $name;
    }

    /**
     * @param $tid
     * @return string
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public static function getTaxonomyTermDescription($tid) {
        $name = '';
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
        if(sizeof($term)>0) {
            $name = $term->getDescription();
        }
        return $name;
    }
}
