<?php

/*
 * This file is part of Symplify
 * Copyright (c) 2016 Tomas Votruba (http://tomasvotruba.cz).
 */

namespace Symplify\PHP7_CodeSniffer\Ruleset\ToSniffNormalizer;

use Nette\Utils\Strings;
use SimpleXMLElement;
use Symplify\PHP7_CodeSniffer\Contract\Ruleset\ToSniffNormalizer\ToSniffNormalizerInterface;
use Symplify\PHP7_CodeSniffer\Ruleset\Extractor\CustomPropertyValuesExtractor;
use Symplify\PHP7_CodeSniffer\Ruleset\Rule\ReferenceNormalizer;
use Symplify\PHP7_CodeSniffer\Sniff\Finder\SniffFinder;

final class RulesetXmlToSniffNormalizer implements ToSniffNormalizerInterface
{
    /**
     * @var SniffFinder
     */
    private $sniffFinder;

    /**
     * @var CustomPropertyValuesExtractor
     */
    private $customPropertyValuesExtractor;

    /**
     * @var ReferenceNormalizer
     */
    private $referenceNormalizer;

    public function __construct(
        SniffFinder $sniffFinder,
        CustomPropertyValuesExtractor $customPropertyValuesExtractor
    ) {
        $this->sniffFinder = $sniffFinder;
        $this->customPropertyValuesExtractor = $customPropertyValuesExtractor;
    }

    public function setReferenceNormalizer(ReferenceNormalizer $referenceNormalizer)
    {
        $this->referenceNormalizer = $referenceNormalizer;
    }

    public function isMatch(string $reference) : bool
    {
        return Strings::endsWith($reference, 'ruleset.xml');
    }

    public function normalize(string $reference) : array
    {
        return $this->buildFromRulesetXml($reference);
    }

    private function buildFromRulesetXml(string $rulesetXmlFile) : array
    {
        $rulesetXml = simplexml_load_file($rulesetXmlFile);

        $includedSniffs = [];
        $excludedSniffs = [];

        $ruleset = $this->customPropertyValuesExtractor->extractFromRulesetXmlFile($rulesetXmlFile);
//        dump($ruleset);

        foreach ($rulesetXml->rule as $rule) {
            if (!isset($rule['ref'])) {
                continue;
            }

            $expandedSniffs = $this->referenceNormalizer->normalize($rule['ref']);
            $includedSniffs = array_merge($includedSniffs, $expandedSniffs);
            $excludedSniffs = $this->processExcludedRules($excludedSniffs, $rule);
        }

        $ownSniffs = $this->getOwnSniffsFromRuleset($rulesetXmlFile);
        $includedSniffs = array_unique(array_merge($ownSniffs, $includedSniffs));
        $excludedSniffs = array_unique($excludedSniffs);

        $sniffs = $this->filterOutExcludedSniffs($includedSniffs, $excludedSniffs);

        $sniffs = $this->sortSniffs($sniffs);

        // todo: decorate with custom rules!

        return $sniffs;
    }

    /**
     * @return string[]
     */
    private function getOwnSniffsFromRuleset(string $rulesetXml) : array
    {
        $rulesetDir = dirname($rulesetXml);
        $sniffDir = $rulesetDir.DIRECTORY_SEPARATOR.'Sniffs';
        if (is_dir($sniffDir)) {
            return $this->sniffFinder->findAllSniffClassesInDirectory($sniffDir);
        }

        return [];
    }

    private function processExcludedRules($excludedSniffs, SimpleXMLElement $rule) : array
    {
        if (isset($rule->exclude)) {
            foreach ($rule->exclude as $exclude) {
                $excludedSniffs = array_merge(
                    $excludedSniffs,
                    $this->referenceNormalizer->normalize($exclude['name'])
                );
            }
        }

        return $excludedSniffs;
    }

    private function filterOutExcludedSniffs(array $includedSniffs, array $excludedSniffs) : array
    {
        $sniffs = [];
        foreach ($includedSniffs as $sniffCode => $sniffClass) {
            if (!in_array($sniffCode, $excludedSniffs)) {
                $sniffs[$sniffCode] = $sniffClass;
            }
        }

        return $sniffs;
    }

    private function sortSniffs(array $sniffs) : array
    {
        ksort($sniffs);
        return $sniffs;
    }
}
