<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Acts as an adapter between typo3 framework and Extension
 *
 * enable easy testing and upgrade path for future typo3 versions
 */
interface SiteResolverInterface
{
    /**
     * Get Site and language-specific base URI for a page
     *
     * @param int $pageUid Page UID to resolve language for
     * @param int $languageId Language ID
     * @return string Language base URI (e.g., "/de", "/en", "/" "/base/en/")
     * @throws ShortNrSiteFinderException When site/language cannot be resolved
     */
    public function getSiteBaseUri(int $pageUid, int $languageId): string;

    /**
     * [LanguageId => SiteLanguage]
     * @param Site $site
     * @return array<int, SiteLanguage>
     */
    public function getLanguagesBySite(Site $site): array;

    /**
     * [LanguageId => SiteLanguage]
     * @param ServerRequestInterface $request
     * @return array<int, SiteLanguage>
     */
    public function getLanguagesByRequest(ServerRequestInterface $request): array;

    /**
     * [LanguageId => SiteLanguage]
     * @param int $rootPageUid
     * @return array<int, SiteLanguage>
     */
    public function getLanguagesByRootPageUid(int $rootPageUid): array;
}
