<?php

namespace Real\RealEstateBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        $xml = new \SimpleXMLElement($result);

        $output->writeln(print_r($xml->html->body->div[1], true));

        $base = 'http://www.zillow.com/webservice/GetSearchResults.htm';

        $output->writeln("[" . $zip . '] Completed crawl');
    }
}
