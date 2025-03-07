<?php 

namespace SushidevTeam\Fairu\Http\Controllers;

use Illuminate\Routing\Controller;

class AssetController extends Controller{

    public function index()
    {
        return redirect(config('fairu.url')."/folders");
    }

}