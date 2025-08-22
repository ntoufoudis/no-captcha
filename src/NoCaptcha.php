<?php

declare(strict_types=1);

namespace Ntoufoudis\NoCaptcha;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Request;

class NoCaptcha
{
    const string CLIENT_API = 'https://www.google.com/recaptcha/api.js';

    const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * The recaptcha secret key.
     */
    protected string $secret;

    /**
     * The recaptcha sitekey key.
     */
    protected string $siteKey;

    protected Client $http;

    /**
     * @var string[]
     */
    protected array $verifiedResponses = [];

    /**
     * NoCaptcha.
     *
     * @param  string[]  $options
     */
    public function __construct(string $secret, string $siteKey, array $options = [])
    {
        $this->secret = $secret;
        $this->siteKey = $siteKey;
        $this->http = new Client($options);
    }

    /**
     * Render HTML captcha
     *
     * @param  string[]  $attributes
     */
    public function display(array $attributes = []): string
    {
        $attributes = $this->prepareAttributes($attributes);

        return '<div'.$this->buildAttributes($attributes).'></div>';
    }

    /**
     * @param  string[]  $attributes
     */
    public function displayWidget(array $attributes = []): string
    {
        return $this->display($attributes);
    }

    /**
     * Display an Invisible reCAPTCHA by embedding a callback into a form submit button.
     *
     * @param  string  $formIdentifier  The HTML ID of the form that should be submitted.
     * @param  string  $text  The text inside the form button.
     * @param  string[]  $attributes  Array of additional HTML elements.
     */
    public function displaySubmit(string $formIdentifier, string $text = 'submit', array $attributes = []): string
    {
        $javascript = '';
        if (! isset($attributes['data-callback'])) {
            $functionName = 'onSubmit'.str_replace(['-', '=', '\'', '"', '<', '>', '`'], '', $formIdentifier);
            $attributes['data-callback'] = $functionName;
            $javascript = sprintf(
                '<script>function %s(){document.getElementById("%s").submit();}</script>',
                $functionName,
                $formIdentifier
            );
        }

        $attributes = $this->prepareAttributes($attributes);

        $button = sprintf('<button%s><span>%s</span></button>', $this->buildAttributes($attributes), $text);

        return $button.$javascript;
    }

    /**
     * Render js source
     *
     * @param  null  $lang
     * @param  bool  $callback
     * @param  string  $onLoadClass
     * @return string
     */
    public function renderJs($lang = null, $callback = false, $onLoadClass = 'onloadCallBack')
    {
        return '<script src="'.$this->getJsLink($lang, $callback, $onLoadClass).'" async defer></script>'."\n";
    }

    /**
     * Verify no-captcha response.
     *
     * @throws GuzzleException
     */
    public function verifyResponse(string $response, string $clientIp = ''): bool
    {
        if (empty($response)) {
            return false;
        }

        // Return true if response already verified before.
        if (in_array($response, $this->verifiedResponses)) {
            return true;
        }

        $verifyResponse = $this->sendRequestVerify([
            'secret' => $this->secret,
            'response' => $response,
            'remoteip' => $clientIp,
        ]);

        if (isset($verifyResponse['success']) && $verifyResponse['success']) {
            // A response can only be verified once from Google, so we need to
            // cache it to make it work in case we want to verify it multiple times.
            $this->verifiedResponses[] = $response;

            return true;
        } else {
            return false;
        }
    }

    /**
     * Verify no-captcha response by Symfony Request.
     */
    public function verifyRequest(Request $request): bool
    {
        return $this->verifyResponse(
            $request->get('g-recaptcha-response'),
            $request->getClientIp()
        );
    }

    /**
     * Get recaptcha js link.
     */
    public function getJsLink(
        ?string $lang = null,
        bool $callback = false,
        string $onLoadClass = 'onloadCallBack'
    ): string {
        $client_api = static::CLIENT_API;
        $params = [];

        $callback ? $this->setCallBackParams($params, $onLoadClass) : false;
        $lang ? $params['hl'] = $lang : null;

        return $client_api.'?'.http_build_query($params);
    }

    /**
     * @param  string[]  $params
     * @param  string  $onLoadClass
     * @return void
     */
    protected function setCallBackParams(&$params, $onLoadClass)
    {
        $params['render'] = 'explicit';
        $params['onload'] = $onLoadClass;
    }

    /**
     * Send verify request.
     *
     * @param  string[]  $query
     * @return string[]
     *
     * @throws GuzzleException
     */
    protected function sendRequestVerify(array $query = []): array
    {
        $response = $this->http->request('POST', static::VERIFY_URL, [
            'form_params' => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Prepare HTML attributes and assure that the correct classes and attributes for captcha are inserted.
     *
     * @param  string[]  $attributes
     * @return string[]
     */
    protected function prepareAttributes(array $attributes): array
    {
        $attributes['data-sitekey'] = $this->siteKey;
        if (! isset($attributes['class'])) {
            $attributes['class'] = '';
        }
        $attributes['class'] = trim('g-recaptcha '.$attributes['class']);

        return $attributes;
    }

    /**
     * Build HTML attributes.
     *
     * @param  string[]  $attributes
     */
    protected function buildAttributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            $html[] = $key.'="'.$value.'"';
        }

        return count($html) ? ' '.implode(' ', $html) : '';
    }
}
