<?php

namespace SSRS\Object;

/**
 * SSRS\Object\Abstract
 *
 * @author arron
 */
class CatalogItems extends ArrayIterator {

    public $iteratorKey = 'CatalogItems';

    public function init() {
        $this->data['CatalogItems'] = array();
    }

    public function setCatalogItems(\stdClass $items) {
        if(isset($items->CatalogItem) && !is_array($items->CatalogItem)){
            $tmp = $items->CatalogItem;
            unset($items->CatalogItem);
            $items->CatalogItem[0] = $tmp;
        }
        if(isset($items->CatalogItem) && $items->CatalogItem) {
            foreach ($items->CatalogItem AS $item) {
                $this->addCatalogItem(new CatalogItem($item));
            }
        }
    }

    public function addCatalogItem(CatalogItem $item) {
        $this->data['CatalogItems'][] = $item;
    }

}
