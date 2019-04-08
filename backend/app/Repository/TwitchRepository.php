<?php
/**
 * Created by IntelliJ IDEA.
 * User: chibuzorogbu
 * Date: 2019-04-08
 * Time: 09:07
 */
namespace App\Repository;

use Illuminate\Support\Facades\Log;
use NewTwitchApi\NewTwitchApi;

class TwitchRepository
{

    /**
     * @var NewTwitchApi|Null
     */
    private $twitchApi;

    /**@var string **/
    protected $redirectUri;


    private const  SCOPES = 'user:edit+viewing_activity_read+openid+channel:read:subscriptions+bits:read+channel_subscriptions+channel_read';




    public function __construct(NewTwitchApi $twitchApi, string $redirectUri)
    {
        $this->twitchApi = $twitchApi;
        $this->redirectUri = $redirectUri;

    }


    /**
     * Gets ouath2  authorization url for Activation Code workflow
     * @return string
     */
    final public function getAuthCodeUrl(): string
    {
        return $this->twitchApi->getOauthApi()->getAuthUrl(
            $this->redirectUri,
            'code',
            self::SCOPES,
            false
        );
    }


    /**
     * converts activation code into access tokens
     * @link https://dev.twitch.tv/docs/authentication/getting-tokens-oauth/#oauth-authorization-code-flow
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    final public function getAccessToken($authCode)
    {
        return $this->twitchApi->getOauthApi()->getUserAccessToken(
            $authCode,
            $this->redirectUri
        );
    }


    /**
     * @link https://dev.twitch.tv/docs/api/reference/#get-streams
     * @param string $channel
     * @param string $token
     *
     * @return array
     */
    final public function captureStream(string $channel): array
    {
        try {
            $userStream = $this->getUserStream($channel);
        } catch (GuzzleException $exception) {
            Log::alert("Unable to get user stream: $channel. Please check the service is available and request is valid",
                ['apiResponse' => $exception->getMessage()]);
            throw new InvalidArgumentException('Bad Request!', 400);
        }

        $userData = json_decode($userStream);
        $data = [];
        if (true === !empty($userData->data)) {
            $events = array_slice($userData->data, 0, 10); //retrieve 10 latest events
            $eventClone = $events;

            $userId = array_shift($eventClone)->user_id;

            Log::debug('Capture Stream Found user with id: ' . $userId);

            foreach ($events as $event) {
                $data[] = [
                    'message'   => sprintf('%s viewers', $event->viewer_count),
                    'thumbnail' => strtr($event->thumbnail_url, ['{width}' => 40, '{height}' => 40]),
                    'userId'    => $event->user_id,
                    'title'     => sprintf('%s: %s [%s]', $event->user_name, $event->title, $event->type),
                ];
            }
        }

        return $data;
    }

    /**
     * @link https://dev.twitch.tv/docs/api/webhooks-guide/#subscriptions
     *
     * @param string $userId
     * @param string $token
     */
    public function registerWebhook(string $userId, string $token): void
    {
        Log::debug('Registering via webhook with callback uri: ' . $this->callBackStreamUri);
        $this->twitchApi->getWebhooksSubscriptionApi()->subscribeToStream($userId, $token, $this->callBackStreamUri);
    }

    /**
     * @param string $username
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    final protected function getUserStream(string $username)
    {
        return $this->twitchApi->getStreamsApi()->getStreamForUsername($username)->getBody()->getContents();
    }



}
