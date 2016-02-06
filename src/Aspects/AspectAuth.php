<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;
use Composer\Downloader;

/**
 * Authentication aspects.
 */
class AspectAuth implements SplObserver
{
    public function update(SplSubject $ev)
    {
        switch ((string)$ev) {
            case 'pre-download':
                $this->before($ev->refRequest());
                break;
            case 'post-download':
                $this->after($ev->refResponse());
                break;
        }
    }

    public function before(HttpGetRequest $req)
    {
        if (!$req->username || !$req->password) {
            $req->username = $req->password = null;
            return;
        }

        if ($req instanceof GitHubRequest && $req->password === 'x-oauth-basic') {
            $req->query['access_token'] = $req->username;
            // forbid basic-auth
            $req->username = $req->password = null;
            return;
        }

        if ($req instanceof GitLabRequest && $req->password === 'oauth2') {
            $req->headers[] = 'Authorization: Bearer ' . $req->username;
            // forbid basic-auth
            $req->username = $req->password = null;
            return;
        }
    }

    public function after(HttpGetResponse $res)
    {
        if (CURLE_OK !== $res->errno) {
            throw new Downloader\TransportException("$res->error:$res->errno");
        }

        switch ($res->info['http_code']) {
            case 200: //OK
                return;
            case 401: //Unauthorized
            case 403: //Forbidden
            case 404: //Not Found
                $res->setNeedAuth();
                break;
            case 407: //Proxy Authentication Required
                break;
        }
    }
}
