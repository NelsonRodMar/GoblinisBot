<?php


namespace App\Service;


use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TwitterApiService
{
    private $getParams;

    public function __construct(ParameterBagInterface $getParams)
    {
        $this->getParams = $getParams;
    }

    /**
     * Make a new Twitter post
     *
     * @param string $textContent
     * @param string $mediaIdContent
     *
     * @return bool
     */
    public function newTweet(string $textContent, string $mediaIdContent): bool
    {
        $consumerKey = $this->getParams->get('TWITTER_CONSUMER_KEY');
        $consumerSecret = $this->getParams->get('TWITTER_CONSUMER_SECRET');
        $accesToken = $this->getParams->get('TWITTER_ACCESS_TOKEN');
        $accesTokenSecret = $this->getParams->get('TWITTER_ACCESS_TOKEN_SECRET');
        $connection = new TwitterOAuth($consumerKey, $consumerSecret, $accesToken, $accesTokenSecret);

        $connection->post("statuses/update", [
            "status" => $textContent,
            "media_ids" => [$mediaIdContent]
        ]);

        if ($connection->getLastHttpCode() == 200) {
            return true;
        } else {
            throw new \ErrorException("Impossible to post new tweet");
        }
    }

    /**
     * Upload image on Twitter
     *
     * @param string $imageUrl the url of the picture
     *
     * @return string|null return Twitter media URL
     * @throws \Exception
     */
    public function postUploadImage(string $imageUrl): ?string
    {
        $consumerKey = $this->getParams->get('TWITTER_CONSUMER_KEY');
        $consumerSecret = $this->getParams->get('TWITTER_CONSUMER_SECRET');
        $accesToken = $this->getParams->get('TWITTER_ACCESS_TOKEN');
        $accesTokenSecret = $this->getParams->get('TWITTER_ACCESS_TOKEN_SECRET');
        $connection = new TwitterOAuth($consumerKey, $consumerSecret, $accesToken, $accesTokenSecret);

        $result = $connection->upload("media/upload", [
            "media" => $imageUrl,
        ]);

        if ($connection->getLastHttpCode() == 200) {
            return $result->media_id_string;
        } else {
            throw new \ErrorException("Impossible to upload image on Twitter");
        }
    }

    /**
     * Return the DateTime of the last tweet of the account
     *
     * @return \DateTime|null
     * @throws \Exception
     */
    public function getLastTweetDateTime(): ?\DateTime
    {
        $consumerKey = $this->getParams->get('TWITTER_CONSUMER_KEY');
        $consumerSecret = $this->getParams->get('TWITTER_CONSUMER_SECRET');
        $accesToken = $this->getParams->get('TWITTER_ACCESS_TOKEN');
        $accesTokenSecret = $this->getParams->get('TWITTER_ACCESS_TOKEN_SECRET');
        $connection = new TwitterOAuth($consumerKey, $consumerSecret, $accesToken, $accesTokenSecret);
        $userId = $this->getParams->get('TWITTER_USER_ID');

        $result = $connection->get("statuses/user_timeline", [
            "user_id" => $userId,
            "count" => 1,
        ]);

        if ($connection->getLastHttpCode() == 200) {
            return new \DateTime($result[0]->created_at);
        } else {
            return null;
        }
    }
}