<?php

/**
 * Class WC_Cevnn_Settings
 */
class WC_Cevnn_Settings
{

    /**
     * @var
     */
    private $apiKey;

    /**
     * @var
     */
    private $IframeKey;

    /**
     * @var
     */
    private $description;

    /**
     * @var
     */
    private $title;

    /**
     * @var
     */
    private $payment_page_name;


    /**
     * WC_Cevnn_Settings constructor.
     * @param $apiKey
     * @param $IframeKey
     * @param $description
     * @param $title
     * @param $payment_page_name
     */
    public  function __construct($apiKey, $IframeKey, $description, $title, $payment_page_name){
        $this->apiKey = $apiKey;
        $this->IframeKey = $IframeKey;
        $this->description =  $description;
        $this->title = $title;
        $this->payment_page_name = $payment_page_name;

    }


    /**
     * @return mixed
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * Returns the Iframe key
     * @return the Iframe key
     */
    public function getIframeKey()
    {
        return $this->IframeKey;
    }

    /**
     * Sets the Iframe key
     * @param the new value of Iframe to set
     */
    public function setIframeKey($IframeKey)
    {
        $this->IframeKey = $IframeKey;
    }

    /**
     * Returns the description
     * @return the description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the new value of the description
     * @param $description the new value to set
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Returns the value of the title
     * @return the value of the title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the new value of the title
     * @param $title the new value to set
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Returns the payment page name
     * @return the payment page name
     */
    public function get_payment_page_name()
    {
        return $this->payment_page_name;
    }

    /**
     * Set the new value of the page name
     * @param $payment_page_name the new value to set
     */
    public function set_payment_page_name($payment_page_name)
    {
        $this->payment_page_name = $payment_page_name;
    }



}