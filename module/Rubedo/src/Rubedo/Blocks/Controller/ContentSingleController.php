<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Blocks\Controller;

use Alb\OEmbed;
use Rubedo\Services\Cache;
use Rubedo\Services\Manager;
use WebTales\MongoFilters\Filter;
use Zend\Debug\Debug;
use Zend\View\Model\JsonModel;

/**
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class ContentSingleController extends AbstractController
{

    public function indexAction()
    {
        $this->_dataReader = Manager::getService('Contents');
        $this->_typeReader = Manager::getService('ContentTypes');
        $site = $this->params()->fromQuery('site');
        $blockConfig = $this->params()->fromQuery('block-config');
        $output = $this->params()->fromQuery();
        $output["blockConfig"] = &$blockConfig;

        $mongoId = $this->params()->fromQuery('content-id');
        if (isset($output["blockConfig"]["contentId"])) {
            $mongoId = $output["blockConfig"]["contentId"];
        }
        $frontOfficeTemplatesService = Manager::getService('FrontOfficeTemplates');

        if (isset($mongoId) && $mongoId != 0) {
            $content = $this->_dataReader->findById($mongoId, true, false);
            if (!$content) {
                $template = $frontOfficeTemplatesService->getFileThemePath("blocks/single/noContent.html.twig");
                return $this->_sendResponse($output, $template);
            }

            $config=Manager::getService("config");
            if ((isset($config["rubedo_config"]["activateMagic"]))&&($config["rubedo_config"]["activateMagic"]=="1")){
                $currentTime=Manager::getService("CurrentTime")->getCurrentTime();
                //get user fingerprint
                $fingerprint=Manager::getService("Session")->get("fingerprint");
                //log content view
                if ($fingerprint){
                    Manager::getService("ContentViewLog")->log($content['id'], $content['locale'], $fingerprint, $currentTime);
                }
                //rebuild user recommendations if necessary
                $emptyFilter=Filter::factory();
                $oldestView=Manager::getService("ContentViewLog")->findOne($emptyFilter);
                if ($oldestView) {
                    $currentTime=Manager::getService("CurrentTime")->getCurrentTime();
                    $timeSinceLastRun=$currentTime-$oldestView['timestamp'];
                    if ($timeSinceLastRun>60){
                        $curlUrl="http://".$_SERVER['HTTP_HOST']."/queue?service=UserRecommendations&class=build";
                        $curly=curl_init();
                        curl_setopt($curly, CURLOPT_URL, $curlUrl);
                        curl_setopt($curly, CURLOPT_FOLLOWLOCATION, true);  // Follow the redirects (needed for mod_rewrite)
                        curl_setopt($curly, CURLOPT_HEADER, false);         // Don't retrieve headers
                        curl_setopt($curly, CURLOPT_NOBODY, true);          // Don't retrieve the body
                        curl_setopt($curly, CURLOPT_RETURNTRANSFER, true);  // Return from curl_exec rather than echoing
                        curl_setopt($curly, CURLOPT_FRESH_CONNECT, true);   // Always ensure the connection is fresh
                        // Timeout super fast once connected, so it goes into async.
                        curl_setopt( $curly, CURLOPT_TIMEOUT, 1 );
                        curl_exec( $curly );
                    }
                }
            }

            $data = $content['fields'];
            $termsArray = array();
            if (isset($content['taxonomy']) && is_array($content['taxonomy'])) {
                foreach ($content['taxonomy'] as $key => $terms) {
                    if ($key == 'navigation') {
                        continue;
                    }

                    if (!is_array($terms) && is_string($terms)) {
                        $terms = array(
                            $terms
                        );
                    }
                    if (is_array($terms)) {
                        foreach ($terms as $term) {
                            $readTerm = Manager::getService('TaxonomyTerms')->getTerm($term);

                            if ($readTerm === null) {
                                $readTerm = array();
                            }

                            foreach ($readTerm as $key => $value) {
                                $termsArray[$key][] = $value;
                            }
                        }
                    }
                }
            }
            $data['terms'] = $termsArray;
            $data["id"] = $mongoId;
            $data['locale'] = Manager::getService('CurrentLocalization')->getCurrentLocalization();

            $type = $this->_typeReader->findById($content['typeId'], true, false);
            $cTypeArray = array();
            $variantTypeArray = array();
            $CKEConfigArray = array();
            $contentTitlesArray = array();
            foreach ($type["fields"] as $value) {

                if ((isset($value['config']['useAsVariation'])) && ($value['config']['useAsVariation'] === true)) {
                    $variantTypeArray[$value['config']['name']] = $value;
                } else {
                    $cTypeArray[$value['config']['name']] = $value;
                }

                if ($value["cType"] == "DCEField") {
                    if (is_array($data[$value['config']['name']])) {
                        $contentTitlesArray[$value['config']['name']] = array();
                        foreach ($data[$value['config']['name']] as $intermedValue) {
                            $intermedContent = $this->_dataReader->findById($intermedValue, true, false);
                            $contentTitlesArray[$value['config']['name']][] = $intermedContent['fields']['text'];
                        }
                    } elseif (is_string($data[$value['config']['name']]) && preg_match('/[\dabcdef]{24}/', $data[$value['config']['name']]) == 1) {
                        $intermedContent = $this->_dataReader->findById($data[$value['config']['name']], true, false);
                        $contentTitlesArray[$value['config']['name']] = $intermedContent['fields']['text'];
                    }
                } elseif (($value["cType"] == "CKEField") && (isset($value["config"]["CKETBConfig"]))) {
                    $CKEConfigArray[$value['config']['name']] = $value["config"]["CKETBConfig"];
                } elseif (($value["cType"] == "externalMediaField") && (isset($data[$value["config"]["name"]]))) {

                    if ($value['config']['multivalued']) {
                        $output['item'] = array();
                        foreach ($data[$value["config"]["name"]] as $externalMedia) {
                            $output['item'][] = $this->getExternalMedia($blockConfig, $externalMedia);
                        }
                    } else {
                        $output['item'] = $this->getExternalMedia($blockConfig, $data[$value["config"]["name"]]);
                    }
                }
            }
            if (isset($type['code']) && !empty($type['code'])) {
                $templateName = $type['code'] . ".html.twig";
            } else {
                $templateName = preg_replace('#[^a-zA-Z]#', '', $type["type"]);
                $templateName .= ".html.twig";
            }
            $hasCustomLayout = false;
            $customLayoutRows = array();
            if (isset($type['layouts']) && is_array($type['layouts'])) {
                foreach ($type['layouts'] as $value) {
                    if (($value['type'] == "Detail") && ($value['active']) && ($value['site'] == $site['id'])) {
                        $hasCustomLayout = true;
                        $customLayoutRows = $value['rows'];
                    }
                }
            }
            $output["data"] = $data;
            $output["isProduct"] = false;

            $output["customLayoutRows"] = $customLayoutRows;
            $output['activateDisqus'] = isset($type['activateDisqus']) ? $type['activateDisqus'] : false;
            $output["type"] = $cTypeArray;
            $output["variantType"] = $variantTypeArray;
            $output["CKEFields"] = $CKEConfigArray;
            $output["contentTitles"] = $contentTitlesArray;

            $js = array(
                $this->getRequest()->getBasePath() . '/' . $frontOfficeTemplatesService->getFileThemePath("js/rubedo-map.js"),
                $this->getRequest()->getBasePath() . '/' . $frontOfficeTemplatesService->getFileThemePath("js/map.js"),
                $this->getRequest()->getBasePath() . '/' . $frontOfficeTemplatesService->getFileThemePath("js/rating.js")
            );
            if ((isset($content['isProduct'])) && ($content['isProduct'] === true)) {
                $output["isProduct"] = true;
                foreach ($content['productProperties']['variations'] as &$variation) {
                    if (isset($variation['specialOffers'])) {
                        $variation['specialOffer'] = $this->getBetterSpecialOffer($variation['specialOffers'], $variation['price']);
                    }
                }
                $output["productProperties"] = $content['productProperties'];
                $output["productProperties"]["manageStock"] = $type["manageStock"];
                $output["initialVariant"] = $content['productProperties']['variations'][0];
                $js[] = $this->getRequest()->getBasePath() . '/' . $frontOfficeTemplatesService->getFileThemePath("js/productdetail.js");
            }

            if (isset($blockConfig['displayType']) && !empty($blockConfig['displayType'])) {
                $template = $frontOfficeTemplatesService->getFileThemePath("blocks/" . $blockConfig['displayType'] . ".html.twig");
            } else if ($hasCustomLayout) {
                $template = $frontOfficeTemplatesService->getFileThemePath("blocks/single/customLayout.html.twig");
            } else {
                $template = $frontOfficeTemplatesService->getFileThemePath("blocks/single/" . $templateName);

                if (!Manager::getService('FrontOfficeTemplates')->templateFileExists($template)) {
                    $template = $frontOfficeTemplatesService->getFileThemePath("blocks/single/default.html.twig");
                }
            }
        } else {
            $template = $frontOfficeTemplatesService->getFileThemePath("blocks/single/noContent.html.twig");
            $js = array();
        }

        $css = array(
            "/components/jquery/timepicker/jquery.ui.timepicker.css",
            "/components/jquery/jqueryui/themes/base/jquery-ui.css"
        );
        return $this->_sendResponse($output, $template, $css, $js);
    }

    public function getContentsAction()
    {
        $this->_dataReader = Manager::getService('Contents');
        $returnArray = array();
        $data = $this->params()->fromQuery();
        if (isset($data['block']['contentId']) && !empty($data['block']['contentId'])) {
            $content = $this->_dataReader->findById($data['block']['contentId']);
            $returnArray[] = array(
                'text' => $content['text'],
                'id' => $content['id']
            );
            $returnArray['total'] = count($returnArray);
            $returnArray["success"] = true;
        } else {
            $returnArray = array(
                "success" => false,
                "msg" => "No query found"
            );
        }
        return new JsonModel($returnArray);
    }

    protected function getBetterSpecialOffer($offers, $basePrice)
    {
        $offerPrice = null;
        $actualDate = new \DateTime();
        if (empty($offers))
            return null;
        foreach ($offers as $offer) {
            $beginDate = $offer['beginDate'];
            $endDate = $offer['endDate'];
            $offer['beginDate'] = new \DateTime();
            $offer['beginDate']->setTimestamp($beginDate);
            $offer['endDate'] = new \DateTime();
            $offer['endDate']->setTimestamp($endDate);
            if (
                $offer['beginDate'] <= $actualDate
                && $offer['beginDate'] <= $actualDate
                && $basePrice > $offer['price']
                && (null == $offerPrice || $offerPrice > $offer['price'])
            ) {
                $offerPrice = $offer['price'];
            }
        }
        return $offerPrice;
    }

    protected function getExternalMedia($blockConfig, $mediaConfig) {
        $oembedParams = array();
        if (isset($mediaConfig['url'])) {

            $oembedParams['url'] = $mediaConfig['url'];

            /** @var \Zend\Cache\Storage\StorageInterface $cache */
            $cache = Cache::getCache('oembed');

            $oembedParams['maxWidth'] = isset($blockConfig['maxWidth'])?$blockConfig['maxWidth']:0;
            $oembedParams['maxHeight'] = isset($blockConfig['maxHeight'])?$blockConfig['maxHeight']:0;

            $cacheKey = 'oembed_item_' . md5(serialize($oembedParams));
            $loaded = false;
            $item = $cache->getItem($cacheKey, $loaded);

            if (!$loaded) {
                // If the URL come from flickr, we check the URL
                if (stristr($oembedParams['url'], 'www.flickr.com')) {
                    $decomposedUrl = explode("/", $oembedParams['url']);

                    $end = false;

                    // We search the photo identifiant and we remove all parameters after it
                    foreach ($decomposedUrl as $key => $value) {
                        if (is_numeric($value) && strlen($value) === 10) {
                            $end = true;
                            continue;
                        }

                        if ($end) {
                            unset($decomposedUrl[$key]);
                        }
                    }

                    $oembedParams['url'] = implode("/", $decomposedUrl);
                }
                /** @var \Alb\OEmbed\Response $response */
                try {
                    $response = OEmbed\Simple::request($oembedParams['url'], array_intersect_key($oembedParams, array_flip(array('maxWidth', 'maxHeight'))));
                    $item['width'] = $oembedParams['maxWidth'];
                    $item['height'] = $oembedParams['maxHeight'];
                    if (!stristr($oembedParams['url'], 'www.flickr.com')) {
                        $item['html'] = $response->getHtml();
                    } else {
                        $raw = $response->getRaw();
                        if ($oembedParams['maxWidth'] > 0) {
                            $width_ratio = $raw->width / $oembedParams['maxWidth'];
                        } else {
                            $width_ratio = 1;
                        }
                        if ($oembedParams['maxHeight'] > 0) {
                            $height_ratio = $raw->height / $oembedParams['maxHeight'];
                        } else {
                            $height_ratio = 1;
                        }

                        $size = "";
                        if ($width_ratio > $height_ratio) {
                            $size = "width='" . $oembedParams['maxWidth'] . "'";
                        }
                        if ($width_ratio < $height_ratio) {
                            $size = "height='" . $oembedParams['maxHeight'] . "'";
                        }
                        $item['html'] = "<img src='" . $raw->url . "' " . $size . "' title='" . $raw->title . "'>";
                    }

                    $cache->setItem($cacheKey, $item);
                } catch (\Exception $e) {
                    $item['html'] = 'Can\'t find video';
                }
            }
            return $item;
        }
        return null;
    }
}
