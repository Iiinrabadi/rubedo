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

use Rubedo\Services\Manager;
use Zend\Debug\Debug;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

/**
 *
 * @author dfanchon
 * @category Rubedo
 * @package Rubedo
 */
class ShoppingCartController extends AbstractController
{

    public function indexAction ()
    {
        $output = $this->params()->fromQuery();
        $output['blockConfig'] = &$output['block-config'];
        $myCart = Manager::getService("ShoppingCart")->getCurrentCart();
        $processedCart=$this->addCartInfos($myCart);
        $output["cartItems"]=$processedCart['cart'];
        $output["totalAmount"]=$processedCart['totalAmount'];
        $output["totalItems"]=$processedCart['totalItems'];
        $output['cartDetailPage'] = isset($output['block-config']['cartDetailPage']) ? $output['block-config']['cartDetailPage'] : false;
        $output['checkoutPage'] = isset($output['block-config']['checkoutPage']) ? $output['block-config']['checkoutPage'] : false;
        if ($output["cartDetailPage"]) {
            $urlOptions = array(
                'encode' => true,
                'reset' => true
            );

            $output['cartDetailPageUrl'] = $this->url()->fromRoute(null, array(
                'pageId' => $output["cartDetailPage"]
            ), $urlOptions);
        }
        if ($output["checkoutPage"]) {
            $urlOptions = array(
                'encode' => true,
                'reset' => true
            );

            $output['checkoutPageUrl'] = $this->url()->fromRoute(null, array(
                'pageId' => $output["checkoutPage"]
            ), $urlOptions);
        }
        $output['displayMode'] = isset($output['block-config']['displayMode']) ? $output['block-config']['displayMode'] : "button";
        if ($output['displayMode']=="detail") {
            $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/shoppingCartDetail.html.twig");
        } else {
            $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/shoppingCart.html.twig");
        }
        $css = array(
            $this->getRequest()->getBasePath() . '/' . Manager::getService('FrontOfficeTemplates')->getFileThemePath("css/shoppingcart.css")
        );
        $js = array(
            $this->getRequest()->getBasePath() . '/' . Manager::getService('FrontOfficeTemplates')->getFileThemePath("js/shoppingcart.js")
        );
        return $this->_sendResponse($output, $template, $css, $js);
    }

    public function addItemToCartAction () {
        $output = array();
        if ($this->getRequest()->isXmlHttpRequest()){
            $this->init();
            $output['isXhr'] = true;
        }
        $params = $this->params()->fromPost();
        $cartUpdate = Manager::getService("ShoppingCart")->addItemToCart($params["productId"], $params["variationId"], $params["amount"]);
        if ($cartUpdate === false){
            $result = array("success" => false);
            return new JsonModel($result);
        }
        $templateService = Manager::getService('FrontOfficeTemplates');
        $template = $templateService->getFileThemePath("blocks/shoppingCart/productList.html.twig");
        $processedCart = $this->addCartInfos($cartUpdate);
        $output["cartItems"] = $processedCart['cart'];
        $output["totalAmount"] = $processedCart['totalAmount'];
        $output["totalItems"] = $processedCart['totalItems'];
        $results = array();
        $results['html'] = $templateService->render($template, $output);
        $results['totalItems'] = $output["totalItems"];
        $results['totalAmount'] = $output['totalAmount'];
        $results['success'] = true;
        return new JsonModel($results);

    }

    public function removeItemFromCartAction () {
        $output = array();
        if ($this->getRequest()->isXmlHttpRequest()){
            $this->init();
            $output['isXhr'] = true;
        }
        $params=$this->params()->fromPost();
        $cartUpdate = Manager::getService("ShoppingCart")->removeItemFromCart($params["productId"], $params["variationId"], $params["amount"]);
        if ($cartUpdate===false){
            $result=array("success"=>false);
            return new JsonModel($result);
        }
        $templateService = Manager::getService('FrontOfficeTemplates');
        $template = $templateService->getFileThemePath("blocks/shoppingCart/productList.html.twig");
        $processedCart=$this->addCartInfos($cartUpdate);
        $output["cartItems"]=$processedCart['cart'];
        $output["totalAmount"]=$processedCart['totalAmount'];
        $output["totalItems"]=$processedCart['totalItems'];
        $results=array();
        $results['html']=$templateService->render($template, $output);
        $results['totalItems']=$output["totalItems"];
        $results['totalAmount'] = $output['totalAmount'];
        $results['success'] = true;
        return new JsonModel($results);
    }

    protected function addCartInfos ($cart) {
        $totalPrice=0;
        $totalItems=0;
        $ignoredArray=array("price","amount","id","sku","stock", 'basePrice', 'specialOffers');
        /** @var \Rubedo\Interfaces\Collection\IContents $contentsService */
        $contentsService=Manager::getService("Contents");
        $contentsService->switchLocaleFiltered();
        $contentsService->reInit();
        $contentsService->switchLocaleFiltered();
        foreach ($cart as &$value){
            $myContent=$contentsService->findById($value["productId"], true, false);
            if ($myContent){
                $value['title']=$myContent['text'];
                $value['product'] = $myContent;
                $value['subtitle']="";
                $price=0;
                $value['unitPrice'] = $myContent["productProperties"]['basePrice'];
                foreach ($myContent["productProperties"]['variations'] as &$variation) {
                    if ($variation['id']==$value['variationId']) {
                        if (array_key_exists('specialOffers', $variation)) {
                            $variation["price"] = $this->getBetterSpecialOffer($variation['specialOffers'], $variation["price"]);
                            $value['unitPrice'] = $variation["price"];
                        }
                        $price=$variation["price"]*$value["amount"];
                        $totalPrice += $price;
                        $totalItems += $value["amount"];
                        foreach ($variation as $varkey => $varvalue) {
                            if (!in_array($varkey,$ignoredArray)) {
                                $value['subtitle'] .= " " . $varvalue;
                            }
                        }
                    }
                }
                $value['price']=$price;
            }
        }
        return (array(
            "cart"=>$cart,
            "totalAmount"=>$totalPrice,
            "totalItems"=>$totalItems
        ));
    }

    protected function getBetterSpecialOffer($offers, $basePrice) {
        $actualDate = new \DateTime();
        foreach($offers as $offer) {
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
            ) {
                $basePrice = $offer['price'];
            }
        }
        return $basePrice;
    }
}
