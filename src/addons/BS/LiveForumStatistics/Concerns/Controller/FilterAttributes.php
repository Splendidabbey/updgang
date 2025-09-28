<?php

namespace BS\LiveForumStatistics\Concerns\Controller;

trait FilterAttributes
{
    public function filterAttributes()
    {
        $extraAttrs = [];

        $extraAttrsInput = $this->filter([
            'attributes.extra_attr_names' => 'array-str',
            'attributes.extra_attr_values' => 'array-str'
        ]);

        foreach ($extraAttrsInput['attributes.extra_attr_names'] AS $i => $name)
        {
            if (! ($name && isset($extraAttrsInput['attributes.extra_attr_values'][$i])))
            {
                continue;
            }

            $value = $extraAttrsInput['attributes.extra_attr_values'][$i];
            if (! strlen($value))
            {
                continue;
            }

            $extraAttrs[$name] = $value;
        }

        return $extraAttrs;
    }
}