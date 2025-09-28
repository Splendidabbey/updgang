<?php

namespace BS\LiveForumStatistics\Entity\Concerns;

trait HasAddOnIdOption
{
    public function getOptionAddOnId()
    {
        return $this->hasOption('addon_id') && $this->getOption('addon_id')
            ? $this->getOption('addon_id')
            : $this->addon_id;
    }
}