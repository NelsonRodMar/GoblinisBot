<?php


namespace App\Command;


use App\Service\OpenSeaApiService;
use App\Service\TwitterApiService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GoblinisBot extends Command

{
    private $openseaApi;
    private $twitterApi;
    private $getParams;
    private $container;
    protected static $defaultName = 'bot:post';

    /**
     * GoblinisBot constructor.
     *
     * @param TwitterApiService $twitterApi
     * @param OpenSeaApiService $openSeaApi
     * @param ParameterBagInterface $getParams
     * @param ContainerInterface $container
     */
    public function __construct(TwitterApiService $twitterApi, OpenSeaApiService $openSeaApi, ParameterBagInterface $getParams, ContainerInterface $container)
    {
        parent::__construct();
        $this->twitterApi = $twitterApi;
        $this->openseaApi = $openSeaApi;
        $this->getParams = $getParams;
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = new FilesystemAdapter();
        $beginScript = new \DateTime();
        $lastUpdateDateTime = $cache->getItem('date_time');
        // If no DateTime update in cache set by last Tweet
        if (!$lastUpdateDateTime->isHit()) {
            $lastTweetTime = $this->twitterApi->getLastTweetDateTime();
            $lastUpdateDateTime->set($lastTweetTime);
            $cache->save($lastUpdateDateTime);
        }else{
            $lastTweetTime = $this->twitterApi->getLastTweetDateTime();
        }
        $io = new SymfonyStyle($input, $output);
        $this->sales($io, $lastUpdateDateTime);
        // Update DateTime last execution
        $lastUpdateDateTime->set($beginScript);
        $cache->save($lastUpdateDateTime);
        return Command::SUCCESS;
    }




    /**
     * Publish all the sales
     *
     * @param SymfonyStyle $io
     * @param \DateTime $lastUpdateDateTime
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function sales(SymfonyStyle $io, \DateTime $lastUpdateDateTime){
        try {
            $allSales = $this->openseaApi->getListLastSalesAfter($lastUpdateDateTime, 'goblinis');
            $contract = $this->getParams->get('GOBLINIS_CONTRACT_ADRESS');
            $openseaAssetsUrl = $this->getParams->get('OPENSEA_ASSETS_URL');
            $io->info('--- Start tweet new sales (' . $lastUpdateDateTime->format('c') . ') ---');
            foreach ($allSales as $sale) {
                $tokenId = $sale["asset"]["token_id"];
                try {
                    $goblinis = $this->openseaApi->getAllDataNFT($contract, $tokenId);
                } catch (\ErrorException $e) {
                    $io->error($e->getMessage());
                }

                $imagePath = $this->getImageUrl($io, $tokenId, $sale["asset"]["image_original_url"]);
                try {
                    $twitterMediaId = $this->twitterApi->postUploadImage($imagePath);
                } catch (\ErrorException $e) {
                    $io->error($e->getMessage());
                }
                if ($twitterMediaId !== null) {
                    $subType = $type = null;
                    foreach ($goblinis["traits"] as $trait) {
                        if ($trait["trait_type"] == "JABBA TYPE") { //to retrieve type
                            $type = $trait["value"];
                        }
                        if ($trait["trait_type"] == "SUB TYPE") { //to retrieve subType
                            $subType = $trait["value"];
                        }
                    }
                    $textContent = 'Goblinis #' . $tokenId . ' a ' . $subType . ' ' . $type . ' form';
                    $numberOfTokenSale = $sale["total_price"] / pow(10, $sale["payment_token"]["decimals"]);
                    $sellerAdresse = $sale["seller"]["user"]["username"] !== null ? $sale["seller"]["user"]["username"] : substr($sale["seller"]["address"], 0, 8);
                    $buyerAdresse = $sale["winner_account"]["user"]["username"] !== null ? $sale["winner_account"]["user"]["username"] : substr($sale["winner_account"]["address"], 0, 8);
                    $usdPrice = $numberOfTokenSale * $sale["payment_token"]["usd_price"];
                    $textContent .= ' bought for ' . $numberOfTokenSale . ' $' . $sale["payment_token"]["symbol"] . ' (' . round($usdPrice, 2) . '$) by ' . $buyerAdresse . ' from ' . $sellerAdresse . '.' . chr(13) . chr(10) . $openseaAssetsUrl . '/' . $contract . '/' . $tokenId;

                    try {
                        $this->twitterApi->newTweet($textContent, $twitterMediaId);
                        $io->info('[INFO] New tweet for Goblinis #' . $tokenId);
                    } catch (\ErrorException $e) {
                        $io->error($e->getMessage());
                    }
                }
            }
            $io->info('--- End tweet new sales ---');
        } catch (\ErrorException $e) {
            $io->error($e->getMessage());
        }
    }



    /**
     * Return the path for an image by ID send
     *
     * @param SymfonyStyle $io
     * @param int $tokenId
     *
     * @return string
     */
    private function getImageUrl(SymfonyStyle $io, int $tokenId, string $imageUrl): string
    {
        $imageExtension = $this->getParams->get('J48BAFORMS_IMAGE_EXTENSION');
        $imageFolder = $this->getParams->get('J48BAFORMS_IMAGE_FOLDER');
        $projectRoot = $this->container->get('kernel')->getProjectDir();
        $imageName = $tokenId . '.' . $imageExtension;

        // if image is not in local, grab it on a server to save it in local
        if (($data = @file_get_contents($projectRoot . DIRECTORY_SEPARATOR .'public'. DIRECTORY_SEPARATOR. $imageFolder . DIRECTORY_SEPARATOR . $imageName)) === false) {
            $io->info("[INFO] Save image for a J48baforms : " . $tokenId);
            file_put_contents($projectRoot . DIRECTORY_SEPARATOR .'public'. DIRECTORY_SEPARATOR. $imageFolder . DIRECTORY_SEPARATOR . $imageName, file_get_contents($imageUrl));
        }

        return $projectRoot . DIRECTORY_SEPARATOR .'public'. DIRECTORY_SEPARATOR. $imageFolder . DIRECTORY_SEPARATOR . $imageName;
    }
}