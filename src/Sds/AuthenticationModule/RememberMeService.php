<?php
/**
 * @package    Sds
 * @license    MIT
 */
namespace Sds\AuthenticationModule;

use Sds\AuthenticationModule\DataModel\RememberMe;
use Sds\AuthenticationModule\Options\RememberMeService as RememberMeServiceOptions;
use Zend\Http\Headers;
use Zend\Http\Header\SetCookie;
use Zend\Math\Rand;

class RememberMeService implements RememberMeInterface
{

    protected $options;

    protected $requestHeaders;

    protected $responseHeaders;

    public function getOptions() {
        return $this->options;
    }

    public function setOptions($options) {
        if (!$options instanceof RememberMeServiceOptions) {
            $options = new RememberMeServiceOptions($options);
        }
        $this->options = $options;
    }

    public function getRequestHeaders() {
        return $this->requestHeaders;
    }

    public function setRequestHeaders(Headers $requestHeaders) {
        $this->requestHeaders = $requestHeaders;
    }

    public function getResponseHeaders() {
        return $this->responseHeaders;
    }

    public function setResponseHeaders(Headers $responseHeaders) {
        $this->responseHeaders = $responseHeaders;
    }

    public function __construct($options) {
        $this->setOptions($options);
    }

    public function getIdentity(){
        list($series, $token, $identityName) = $this->getCookieValues();
        $documentManager = $this->options->getDocumentManager();
        $repository = $documentManager->getRepository('Sds\AuthenticationModule\DataModel\RememberMe');
        $record = $repository->findOneBy(['series' => $series]);

        if ( ! $record){
            //If no record found matching the cookie, then ignore it, and remove the cookie.
            $this->removeCookie();
            return false;
        }

        if ($record->getIdentityName() != $identityName){
            //Something has gone very wrong if the identityName doesn't match, remove cookie, and db record
            $this->removeCookie();
            $this->removeSeriesRecord();
            return false;
        }

        if ($record->getToken() != $token){
            //If tokens don't match, then session theft has occured. Delete all user records, and cookie.
            $this->removeCookie();
            $this->removeIdentityRecords();
            return false;
        }

        //If we have got this far, then the identity is good.
        //Update the token.

        $newToken = $this->createToken();

        $record->setToken($newToken);
        $documentManager->flush();

        $this->setCookie($series, $newToken, $identityName);

        $identityRepository = $documentManager->getRepository($this->options->getIdentityClass());
        $identityProperty = $this->options->getIdentityProperty();
        $identity = $identityRepository->findOneBy([$identityProperty => $identityName]);
        if (! $identity){
            //although the cookie and rememberme record match, there is no matching registered user!
            $this->removeCookie();
            $this->removeIdentityRecords();
            return false;
        }

        return $identity;
    }

    public function loginSuccess($identity, $rememberMe){

        $this->removeSeriesRecord();

        if ($rememberMe){
            //Set rememberMe cookie
            $series = $this->createSeries();
            $token = $this->createToken();
            $identityName = $identity->{'get' . ucfirst($this->options->getIdentityProperty())}();

            $record = new RememberMe($series, $token, $identityName);

            $documentManager = $this->options->getDocumentManager();
            $documentManager->persist($record);
            $documentManager->flush();

            $this->setCookie($series, $token, $identityName);
        } else {
            $this->removeCookie();
        }
    }

    public function logout(){
        $this->removeSeriesRecord();
        $this->removeCookie();
    }

    protected function setCookie($series, $token, $identityName){

        $cookie = $this->getCookie($this->responseHeaders, true);
        $cookie->setName($this->options->getCookieName());
        $cookie->setValue("$series\n$token\n$identityName");
        $cookie->setExpires(time() + $this->options->getCookieExpire());
        $cookie->setSecure($this->options->getSecureCookie());
    }

    protected function getCookieValues(){

        $cookie = $this->getCookie($this->requestHeaders);
        if ( ! isset($cookie)){
            return;
        }
        return explode("\n", $cookie->getValue());
    }

    protected function removeCookie(){

        $cookie = $this->getCookie($this->responseHeaders, true);

        if (isset($cookie)){
            $cookie->setName($this->options->getCookieName());
            $cookie->setValue('');
            $cookie->setExpires(time() - 3600);
            $cookie->setSecure($this->options->getSecureCookie());
        }
    }

    protected function getCookie($headers, $createIfNotSet = false){

        $cookie = null;

        if ( ! $headers instanceof Headers){
            return $cookie;
        }

        foreach($headers as $header){
            if ($header instanceof SetCookie && $header->getName() == $this->options->getCookieName()){
                $cookie = $header;
                break;
            }
        }
        if ( ! isset($cookie) && $createIfNotSet){
            $cookie = new SetCookie();
            $headers->addHeader($cookie);
        }

        return $cookie;
    }

    protected function removeSeriesRecord(){
        $cookieValues = $this->getCookieValues();
        if ($cookieValues){
            $series = $cookieValues[0];

            //Remove any existing db record
            $this->options->getDocumentManager()
                ->createQueryBuilder('Sds\AuthenticationModule\DataModel\RememberMe')
                ->remove()
                ->field('series')->equals($series)
                ->getQuery()
                ->execute();
        }
    }

    protected function removeIdentityRecords(){
        $cookieValues = $this->getCookieValues();
        if ($cookieValues){
            $identityName = $cookieValues[2];

            //Remove any existing db record
            $this->options->getDocumentManager()
                ->createQueryBuilder('Sds\AuthenticationModule\DataModel\RememberMe')
                ->remove()
                ->field('identityName')->equals($identityName)
                ->getQuery()
                ->execute();
        }
    }

    protected function createToken($length = 32)
    {
        $rand = Rand::getString($length, null, true);
        return $rand;
    }

    protected function createSeries($length = 32)
    {
        $rand = Rand::getString($length, null, true);
        return $rand;
    }
}
