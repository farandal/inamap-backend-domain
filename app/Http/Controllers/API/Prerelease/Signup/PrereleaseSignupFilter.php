<?php

namespace Domain\App\Http\Controllers\API\Prerelease\Signup;

use EloquentFilter\ModelFilter;

class PrereleaseSignupFilter extends ModelFilter
{
    protected $drop_id = false;

    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];

    public function q($generalSearch)
    {
        return $this->where(function ($q) use ($generalSearch) {
            $term = '%' . trim($generalSearch) . '%';
            $q->where('name', 'ILIKE', $term)
                ->orWhere('email', 'ILIKE', $term)
                ->orWhere('contact_phone', 'ILIKE', $term)
                ->orWhere('business_website', 'ILIKE', $term)
                ->orWhere('business_instagram', 'ILIKE', $term);
        });
    }

    public function id($id)
    {
        return $this->where('id', $id);
    }

    public function ids($ids)
    {
        return $this->whereIn('id', $ids);
    }

    public function name($name)
    {
        return $this->whereLike('name', $name);
    }

    public function email($email)
    {
        return $this->whereLike('email', $email);
    }

    public function preferredLanguage($val)
    {
        return $this->where('preferred_language', $val);
    }
}
