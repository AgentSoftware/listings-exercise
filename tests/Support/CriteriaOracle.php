<?php

namespace Tests\Support;

use App\Models\Listing;
use App\Search\ListingCriteria;

/**
 * A deliberately naive statement of "does this listing match these criteria",
 * written in plain PHP, used only to check the two that aren't.
 *
 * The application expresses the rule twice, both times in SQL and both times
 * for speed: ListingCriteria::applyTo() asks "given criteria, which listings?"
 * for the browse page, and the join in GenerateAlerts asks the inverse, "given
 * a listing, which searches?", for the nightly run. Inverting a query by hand
 * is exactly the kind of thing that looks right and isn't.
 *
 * So the tests assert both against this instead of against hand-maintained
 * expectations: it is slow, obvious and easy to check by reading, which is the
 * opposite trade-off to the code it guards. Keeping it out of app/ is the
 * point — nothing in the application calls it, and nothing should.
 */
class CriteriaOracle
{
    public static function matches(ListingCriteria $criteria, Listing $listing): bool
    {
        return ($criteria->propertyType === null || $listing->property_type === $criteria->propertyType)
            && ($criteria->maxPrice === null || $listing->price <= $criteria->maxPrice)
            && ($criteria->minBedrooms === null || $listing->bedrooms >= $criteria->minBedrooms)
            && ($criteria->region === null || $listing->branch->region === $criteria->region);
    }
}
