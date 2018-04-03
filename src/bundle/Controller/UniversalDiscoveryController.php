<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformAdminUiBundle\Controller;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Search\SearchHit;
use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\REST\Common\Output\Visitor;
use Symfony\Component\HttpFoundation\JsonResponse;

class UniversalDiscoveryController extends Controller
{
    /** @var LocationService */
    protected $locationService;

    /** @var SearchService */
    protected $searchService;

    /** @var Visitor */
    protected $visitor;

    /**
     * @param LocationService $locationService
     * @param SearchService $searchService
     * @param Visitor $visitor
     */
    public function __construct(
        LocationService $locationService,
        SearchService $searchService,
        Visitor $visitor
    ) {
        $this->searchService = $searchService;
        $this->locationService = $locationService;
        $this->visitor = $visitor;
    }

    /**
     * @param int $startingLocationId
     * @param int $locationId
     * @param int $limit
     *
     * @return JsonResponse
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function preselectedLocationDataAction(int $startingLocationId, int $locationId, int $limit = 50)
    {
        $location = $this->locationService->loadLocation($locationId);

        $subitemsLocationIds = array_unique([
            $startingLocationId,
            $location->parentLocationId,
            $location->id,
        ]);

        $relativeLocationPath = array_slice(
            $location->path,
            array_search($startingLocationId, $location->path)
        );

        $locations = $this->searchService->findLocations(
            new LocationQuery([
                'filter' => new Query\Criterion\LocationId($relativeLocationPath),
            ])
        );

        $result = [];

        foreach ($locations->searchHits as $location) {
            $result['locations'][$location->valueObject->id] = $this->getRestFormat($location->valueObject);
        }

        foreach ($subitemsLocationIds as $id) {
            $subitems = $this->searchService->findLocations(
                new LocationQuery([
                    'filter' => new Query\Criterion\ParentLocationId($id),
                    'limit' => $limit,
                ])
            );

            $result['subitems'][$id] = [
                'totalCount' => $subitems->totalCount,
                'locations' => array_map(function (SearchHit $searchHit) {
                    return $this->getRestFormat($searchHit->valueObject);
                }, $subitems->searchHits),
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * @param ValueObject $valueObject
     *
     * @return array
     */
    protected function getRestFormat(ValueObject $valueObject)
    {
        return json_decode(
            $this->visitor->visit($valueObject)->getContent()
        );
    }
}