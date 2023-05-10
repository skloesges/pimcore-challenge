<?php

namespace App\Controller;

use \Pimcore\Model\DataObject;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;

class ContentController extends FrontendController
{
    public function myDefaultAction(Request $request)
    {
        $entries = new DataObject\Club\Listing();

        return $this->render('content/clubs_list.html.twig', ["clubs" => $entries]);
    }

    public function clubDetailsAction(Request $request) {
        $clubname = $request->get('clubname');
        $club = new DataObject\Club\Listing();
        $club->setCondition("clubid LIKE ?", "%{$clubname}%");
        $data = $club->load();

        $data = $club->count() != 1
            ? null
            : $data[0];

        return $this->render('content/club_details.html.twig', ["club" => $data]);
    }
}