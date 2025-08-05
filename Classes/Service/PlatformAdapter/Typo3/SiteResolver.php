<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use Throwable;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class SiteResolver implements SiteResolverInterface
{
    private array $siteCache = [];

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly Typo3PageTreeResolver $pageTreeResolver
    )
    {}

    /**
     * returns the base language path of the given page uid
     *
     * @param int $pageUid
     * @param int $languageId
     * @return string
     * @throws ShortNrSiteFinderException
     */
    public function getSiteBaseUri(int $pageUid, int $languageId): string
    {
        return $this->getLanguageByPageUid($pageUid, $languageId)?->getBase()->getPath() ?? '';
    }

    /**
     * get Language on that site where the page is
     *
     * @param int $pageUid
     * @param int $languageId
     * @return SiteLanguage|null
     * @throws ShortNrSiteFinderException
     */
    private function getLanguageByPageUid(int $pageUid, int $languageId): ?SiteLanguage
    {
        try {
            return $this->getSiteByPageUid($pageUid)?->getLanguageById($languageId);
        } catch (Throwable $e) {
            throw new ShortNrSiteFinderException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int $pageUid
     * @return SiteInterface|null
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     * @throws ShortNrSiteFinderException
     * @throws ShortNrTreeProcessorException
     */
    private function getSiteByPageUid(int $pageUid): ?SiteInterface
    {
        // already found return cached version
        if (!empty($this->siteCache[$pageUid])) {
            return $this->siteCache[$pageUid];
        }

        // resolve page to rootPage
        $rootPageId = $this->getRootPageId($pageUid);

        if ($rootPageId > 0) {
            try {
                // load site with root page (very cheap!)
                return $this->siteCache[$pageUid] ??= $this->siteFinder->getSiteByRootPageId($rootPageId);
            } catch (Throwable $e) {
                throw new ShortNrSiteFinderException($e->getMessage(), $e->getCode(), $e);
            }
        }

        throw new ShortNrSiteFinderException('Could not resolve page uid via PageTree (uid: ' . $pageUid. ')');
    }

    /**
     * return the base ROOT-UID of any given page UID
     *
     * uses fast Tree lookup (very cheap)
     *
     * @param int $uid
     * @return int
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     */
    private function getRootPageId(int $uid): int
    {
        return (int)$this->pageTreeResolver->getPageTree()->getItem($uid)?->getBaseTranslation()->getRoot()->getPrimaryId();
    }
}
