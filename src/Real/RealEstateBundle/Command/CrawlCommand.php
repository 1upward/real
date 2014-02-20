<?php

namespace Real\RealEstateBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Doctrine\DBAL\Connection;

class CrawlCommand extends ContainerAwareCommand
{
    private $zwsId = 'X1-ZWz1bbibbwot1n_7ucak';

    public function configure()
    {
        $this->setName("real:realestate:crawl")
            ->setDescription('Crawls Zillow for a given Zip Code');

        $this->addOption(
            'zip',
            null,
            InputOption::VALUE_REQUIRED,
            'Pulls latest data for given zip code'
        );
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('zip'))
            throw new \Exception("Missing Zipcode");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $zip = $input->getOption('zip');
        $output->writeln("[" . $zip . '] Beginning crawl');

        $searchBase = 'http://www.zillow.com/homes/';
        $searchUrl = $searchBase . $zip . '_rb/';

        $output->writeln("URL=" . $searchUrl);

        // start at the beginning and paginate
        $page = 0;

        do {
            $page++;

            $ch = curl_init($searchUrl . '/' . $page . '_p/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);

            $properties = $this->extractProperties($result);

            $base = 'http://www.zillow.com/webservice/GetSearchResults.htm';
            foreach ($properties as $property) {
                // skip undisclosed addresses for now
                if (strpos($property['address'], '(undisclosed') !== false)
                    continue;

                $address = urlencode($property['address']);
                $address = str_replace('&amp;', '&', $address);
                $zip = urlencode($property['citystatezip']);

                $url = $base . '?zws-id=' . $this->zwsId . "&address=$address&citystatezip=$zip&rentzestimate=true";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $detailResult = curl_exec($ch);
                $details = $this->extractDetails($detailResult);

                $url = 'http://www.zillow.com/homes/' . str_replace(' ', '-', str_replace(" #", '', $property['address'])) . '-' . $zip . '_rb/';
                $output->writeln("url=" . $url);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $webDetailResult = curl_exec($ch);
                $webDetails = $this->extractDetailsFromWeb($webDetailResult);
                $details['price'] = $webDetails['price'];

                $output->writeln("[$zip] url=$url,address=$address,citystatezip=$zip,details=" . json_encode($details));

                // store to DB
                $this->storeProperty($details);
            }
        } while (count($properties) > 0);

        $output->writeln("[" . $zip . '] Completed crawl');
    }

    public function storeProperty($details) {
        /** @var Connection $db */
        $db = $this->getContainer()->get("database_connection");
        $query = $db->prepare("REPLACE INTO property (address1, city, state, zip, price, rent_zestimate, rent_zestimate_low, rent_zestimate_high, zestimate_low, zestimate_high, zpid) VALUES
            (:address1, :city, :state, :zip, :price, :rent_zestimate, :rent_zestimate_low, :rent_zestimate_high, :zestimate_low, :zestimate_high, :zpid)");
        $query->bindParam(':address1', $details['address1']);
        $query->bindParam(':city', $details['city']);
        $query->bindParam(':state', $details['state']);
        $query->bindParam(':zip', $details['zip']);
        $query->bindParam(':price', $details['price']);
        $query->bindParam(':rent_zestimate', $details['rent_zestimate']);
        $query->bindParam(':rent_zestimate_low', $details['rent_zestimate_low']);
        $query->bindParam(':rent_zestimate_high', $details['rent_zestimate_high']);
        $query->bindParam(':zestimate_low', $details['zestimate_low']);
        $query->bindParam(':zestimate_high', $details['zestimate_high']);
        $query->bindParam(':zpid', $details['zpid']);
        $query->execute();
    }

    /**
     * Gets a list of properties from a zip code search
     *
     * @param $result
     * @return string[]
     */
    public function extractProperties($result) {
        $dom = HtmlDomParser::str_get_html($result);

        $properties = array();

        $set = $dom->find('span[itemprop=streetAddress]');
        foreach ( $set as $index => $element ) {
            $properties[$index] = array(
                'address' => trim($element->innertext)
            );
        }

        $set = $dom->find('span[itemprop=postalCode]');
        foreach ( $set as $index => $element ) {
            $properties[$index]['citystatezip'] = trim(str_replace('[', '', str_replace(']', '', $element->innertext)));
        }

        return $properties;
    }

    public function extractDetailsFromWeb($result) {
        $dom = HtmlDomParser::str_get_html($result);

        $details = array(
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'price' => 0,
            'rentPrice' => 0,
            'rent_zestimate' => 0,
            'rent_zestimate_low' => 0,
            'rent_zestimate_high' => 0,
            'zestimate_low' => 0,
            'zestimate_high' => 0,
            'zpid' => ''
        );

        $rawdata = $dom->find('dt.price-large');
        foreach ( $rawdata as $data ) {
            $details['price'] = trim(str_replace('$', '', str_replace(',', '', $data->plaintext)));
        }

        return $details;
    }

    public function extractDetails($result) {
        $details = array(
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'price' => 0,
            'rentPrice' => 0,
            'rent_zestimate' => 0,
            'rent_zestimate_low' => 0,
            'rent_zestimate_high' => 0,
            'zestimate_low' => 0,
            'zestimate_high' => 0,
            'zpid' => ''
        );

        $xml = simplexml_load_string($result);

        $data = $xml->response->results->result;
        $details['zpid'] = (string)$data->zpid;
        $details['address1'] = (string)$data->address->street;
        $details['city'] = (string)$data->address->city;
        $details['state'] = (string)$data->address->state;
        $details['zip'] = (string)$data->address->zipcode;
        $details['rent_zestimate'] = @(string)$data->rentzestimate->amount;
        $details['rent_zestimate_low'] = @(string)$data->rentzestimate->valuationRange->low;
        $details['rent_zestimate_high'] = @(string)$data->rentzestimate->valuationRange->high;

        return $details;
    }
}
