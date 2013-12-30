<?php

namespace Real\RealEstateBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Sunra\PhpSimple\HtmlDomParser;

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

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

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

        $base = 'http://www.zillow.com/webservice/GetSearchResults.htm';
        foreach ($properties as $property) {
            $address = urlencode($property['address']);
            $zip = urlencode($property['citystatezip']);
            $url = $base . '?zws-id=' . $this->zwsId . "&address=$address&citystatezip=$zip&rentzestimate=true";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $details = curl_exec($ch);
            $output->writeln("[$zip] address=$address,citystatezip=$zip,url=$url,details=$details");
        }


        $output->writeln("[" . $zip . '] Completed crawl');
    }
}
