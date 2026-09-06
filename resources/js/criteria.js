// The client's copy of what counts as a search criterion.
//
// Mirrors App\Search\ListingCriteria: a blank field is not a filter, and
// neither is a minimum of zero bedrooms — "0 or more" is every listing, and the
// server drops it. Without that rule here, the Save buttons enable for a
// request the server will always reject.
export function activeCriteria(form) {
    return Object.fromEntries(
        Object.entries(form).filter(([field, value]) => {
            if (value === '' || value === null) {
                return false;
            }

            return !(field === 'min_bedrooms' && Number(value) === 0);
        }),
    );
}

export function hasCriteria(form) {
    return Object.keys(activeCriteria(form)).length > 0;
}
