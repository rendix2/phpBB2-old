<?php

namespace phpBB2\Models;

use dibi;
use Tables;

/**
 * Class RanksManager
 *
 * @package phpBB2\Models
 * @author  rendix2
 */
class RanksManager extends CrudManager
{

    public function getSpecialRanksCount()
    {
        return dibi::select('COUNT(rank_id)')
            ->from(Tables::RANKS_TABLE)
            ->where('[rank_special] = %i', 1)
            ->fetchSingle();
    }

    public function getNotSpecialRanksCount()
    {
        return dibi::select('COUNT(rank_id)')
            ->from(Tables::RANKS_TABLE)
            ->where('[rank_special] = %i', 0)
            ->fetchSingle();
    }

    public function getAllSpecialRanks()
    {
        return dibi::select('*')
            ->from(Tables::RANKS_TABLE)
            ->where('[rank_special] = %i', 1)
            ->orderBy('rank_title')
            ->fetchAll();
    }

    public function getPairsSpecialRanks()
    {
        return dibi::select('*')
            ->from(Tables::RANKS_TABLE)
            ->where('[rank_special] = %i', 1)
            ->orderBy('rank_title')
            ->fetchPairs('rank_id', 'rank_title');
    }

}
