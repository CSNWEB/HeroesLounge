<?php namespace Rikki\LoungeViews\Components;

 
use Cms\Classes\ComponentBase;
use Rikki\Heroeslounge\Models\Match;
use Rikki\Heroeslounge\Models\Season;
use Rikki\Heroeslounge\Models\Playoff;
use Redirect;
use Flash;

class PlayoffOverview extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'PlayoffOverview',
            'description' => 'Displays a Playoff'
        ];
    }
    public $season = null;
    public $playoff = null;
    public $matches = null;
    public $polylines = null;
    public $match_height = 3.875;
    public $match_width = 13;
    public $width_between_matches = 2;
    public $total_width = 105; //TODO
    public $total_height = 43.9375; //TODO

    public function init()
    {
        if ($this->param('season-slug')) {
            $this->season = Season::where('slug',$this->param('season-slug'))->first();
            if ($this->season) {
                $this->playoff = $this->season->playoffs()->where('title',$this->param('playoff-title'))->first();
            }
        } else {
            $this->playoff = Playoff::where('title',$this->param('playoff-title'))->first();
        }
        
        if ($this->playoff) {
            if ($this->playoff->type == 'playoffv1') {
                $this->total_height = 43.9375;
                $this->total_width = 105;
            } else if ($this->playoff->type == 'se16') {
                $this->total_height = 43.9375;
                $this->total_width = 60;
            }
            $this->matches = [];
            $this->polylines = [];
            foreach ($this->playoff->matches as $match) {
                $array = [];
                $array['model'] = $match;
                $offset_left = 30;
                $offset_top = 2.6875;
                //for now, special case only
                $offsets = $this->getOffsetsForMatch($match->playoff_position);
                $array['offset_left'] = $offsets['left'];
                $array['offset_top'] = $offsets['top'];
                $this->matches[] = $array;
                if ($match->playoff_winner_next) {
                    $this->addWinnerLinkBetweenMatches($match->playoff_position, $match->playoff_winner_next);
                }
                if ($match->playoff_loser_next) {
                    $this->addLoserLinkToMatch($match->playoff_loser_next);
                }
            }
        }

        $this->addComponent(
            'Rikki\Heroeslounge\Components\RoundMatches',
            'roundMatches',
            [
                'deferredBinding'   => true,
                'showLogo' => true,
                'showName' => true,
                'type' => 'division'
            ]
        );
        $this->addComponent(
            'Rikki\LoungeViews\Components\DivisionTable',
            'divisionTable',
            [
                'deferredBinding'   => true,
                'showScore' => true
            ]
        );
       
    }

    public function onRun()
    {
        $this->addJs('/plugins/rikki/heroeslounge/assets/js/ResizeSensor.js');
        $this->addJs('/plugins/rikki/heroeslounge/assets/js/ElementQueries.js');
        $this->addCss('/plugins/rikki/heroeslounge/assets/css/heroeslounge.css');
    }
    public function defineProperties()
    {
        return [
        ];
    }

    //gets offsets in rem for a playoff_position $pp
    public function getOffsetsForMatch($pp)
    {
        $left = 0;
        $top = 0;
        $round_width = $this->match_width + $this->width_between_matches;
        $dec_position = Match::decodePlayoffPosition($pp);
        if ($this->playoff->type == 'playoffv1') {
            if ($dec_position['bracket'] == 1) {
                //winners bracket
                $left = $round_width + (2 * $round_width * ($dec_position['round'] - 1));
                if ($dec_position['round'] == 1) {
                    if ($dec_position['matchnumber'] == 1) {
                        $top = 2.6875;
                    } else if ($dec_position['matchnumber'] == 2) {
                        $top = 11.9375;
                    }
                } else if ($dec_position['round'] == 2) {
                    $top = 7.3125;
                }
            } else if ($dec_position['bracket'] == 2) {
                //losers bracket
                $left = $round_width * ($dec_position['round'] - 1);
                switch($dec_position['round']) {
                    case 1:
                        switch ($dec_position['matchnumber']) {
                            case 1:
                                $top = 23.5;
                                break;
                            case 2:
                                $top = 28.125;
                                break;
                            case 3:
                                $top = 35.0625;
                                break;
                            case 4:
                                $top = 39.6875;
                                break;
                        }
                        break;
                    case 2:
                        switch ($dec_position['matchnumber']) {
                            case 1:
                                $top = 25.8125;
                                break;
                            case 2:
                                $top = 37.375;
                                break;
                        }
                        break;
                    case 3:
                        switch ($dec_position['matchnumber']) {
                            case 1:
                                $top = 21.1875;
                                break;
                            case 2:
                                $top = 32.75;
                                break;
                        }
                        break;
                    case 4:
                        $top = 26.96875;
                        break;
                    case 5:
                        $top = 18.875;
                        break;
                }
            } else if ($dec_position['bracket'] == 3) {
                //finals
                $left = 5 * $round_width;
                $top = 13.09375;
            }
        } else if ($this->playoff->type == 'se16') {
            //winners bracket only
            $left = $round_width * ($dec_position['round']-1);
            $diff = 4.625;
            $top = 2.6875 + (2**($dec_position['round']-1) - 1) * $diff / 2 + ($dec_position['matchnumber']-1) * $diff* 2**($dec_position['round']-1);
        }
        
        return ['left' => $left, 'top' => $top];
    }

    public function addWinnerLinkBetweenMatches($pp1, $pp2)
    {
        $offsets1 = $this->getOffsetsForMatch($pp1);
        $offsets2 = $this->getOffsetsForMatch($pp2);
        $fourth_x = $offsets2['left'] * 1000;
        $fourth_y = ($offsets2['top'] + (0.5 * $this->match_height)) * 1000;
        $first_x = ($offsets1['left'] + $this->match_width) * 1000;
        $first_y = ($offsets1['top'] + (0.5 * $this->match_height)) * 1000;
        $middle_x = ($offsets2['left'] - (0.5 * $this->width_between_matches)) * 1000;

        $line = [];
        $line[] = $first_x.','.$first_y;
        $line[] = $middle_x.','.$first_y;
        $line[] = $middle_x.','.$fourth_y;
        $line[] = $fourth_x.','.$fourth_y;
        $this->polylines[] = $line;
    }

    public function addLoserLinkToMatch($pp) 
    {
        $offsets = $this->getOffsetsForMatch($pp);
        //1 rem is 1000
        $third_x = $offsets['left'] * 1000;
        $third_y = ($offsets['top'] + (0.5 * $this->match_height)) * 1000;
        $first_x = ($offsets['left'] - (0.5 * $this->width_between_matches)) * 1000;
        $first_y = ($offsets['top'] + (0.25 * $this->match_height)) * 1000;

        $line = [];
        $line[] = $first_x.','.$first_y;
        $line[] = $first_x.','.$third_y;
        $line[] = $third_x.','.$third_y;
        $this->polylines[] = $line;
    }
}