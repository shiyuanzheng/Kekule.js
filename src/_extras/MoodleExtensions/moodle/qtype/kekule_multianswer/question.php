<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');
require_once($CFG->dirroot . '/question/type/shortanswer/question.php');
require_once($CFG->dirroot . '/question/type/kekule_multianswer/lib.php');


/**
 * Represents a Kekule Chem question.
 */
class qtype_kekule_multianswer_question extends question_graded_automatically_with_countback {
    public $questionParts = array();
    public $blankCount = 0;
    public $blanks = array();
    public $subGroups = array();
    public $answers = array();
    public $answerKeyMap = array();
    public $scoreRatioSum = 0;
    // Matching answer keys to currect response
    public $matchingAnswers = array();

    /**
     * Returns detailed hash data of answer.
     * @param string $answerItem
     * @returns array
     */
    protected function getAnswerItemData($answerItem)
    {
        /*
        try
        {
            $result = json_decode($answerItem);   // should be an array of different answer parts
        }
        catch(Exception $e)
        {
            $result = array();
        }
        if (!is_array($result))
            $result = array();
        return $result;
        */
        return $answerItem;
    }

    protected function _extractArrayField($targetArray, $field)
    {
        $result = array();
        foreach($targetArray as $item)
        {
            $result[] = $item->$field;
        }
        return $result;
    }

    protected function getResponseAnswerData($response)
    {
        //var_dump($response);
        //return $this->getAnswerItemData($response['answer']);\
        $result = array();
        foreach ($response as $key => $item)
        {
            $result[] = $this->getAnswerItemData($item);
        }
        return $result;
    }

    protected function getCorrectAnswerSet()
    {
        /*
        var_dump($this->answers);
        die();
        */
        $result = array();
        $maxFractions = array();
        $answers = $this->answers;
        foreach($answers as $key => $answer)
        {
            $blankIndex = $answer->blankindex;
            if (array_key_exists($blankIndex, $maxFractions))
                $maxFraction = $maxFractions[$blankIndex];
            else
                $maxFraction = 0;

            $fraction = $answer->fraction;
            if ($fraction > $maxFraction)
            {
                $maxFractions[$blankIndex] = $fraction;
                $result[$blankIndex] = $answer;
            }
        }
        return $result;
    }

    /**
     * Grade a blank group marked by array of blank indexes (e.g. [2,3]).
     * @param $blankGroup
     * @param $currAnswers
     */
    protected function gradeResponseOfGroup($blankGroup, $responses)
    {
        $responseCount = count($responses);
        // build a score matrix of keyGroups * responses
        $scoreMatrix = array();
        foreach ($blankGroup as $i => $keyIndex)
        {
            $scoreMatrix[$keyIndex] = array();
            $keys = $this->answerKeyMap[$keyIndex];
            foreach($responses as $resIndex => $response)
            {
                list($matchKey, $matchRatio, $matchRatioFraction) = $this->getMatchKeyOfResponse($response, $keys);
                $cell = new stdClass;
                $cell->matchKey = $matchKey;
                $cell->matchRatio = $matchRatio;
                $cell->matchRatioFraction = $matchRatioFraction;
                $scoreMatrix[$keyIndex][$resIndex] = $cell;
            }
        }
        //var_dump($scoreMatrix);
        // find highest match ratio element from matrix, then delete the corresponding row and col
        // do the same job on the remaining matrix, gradually got all results

        while (!empty($scoreMatrix))
        {
            list($keyIndex, $resIndex, $maxCell) = $this->_extractMaxRatioCell($scoreMatrix);
            // save result to corresponding blank
            $blankIndex = $blankGroup[$resIndex];
            $blank = $this->blanks[$blankIndex];
            //var_dump($blank);
            $blank->matchAnswerKey = $maxCell->matchKey;
            $blank->matchRatio = $maxCell->matchRatio;
            if (isset($blank->matchAnswerKey))
                $blank->fraction = $blank->matchAnswerKey->fraction * $maxCell->matchRatio;
            else
                $blank->fraction = 0;
            $blank->score = $this->getBlankDefaultMark($keyIndex) * $maxCell->matchRatio * $blank->fraction;
            //var_dump($blank);
        }
    }
    private function _extractMaxRatioCell(&$scoreMatrix)
    {
        //echo('scoreMatrix: ');
        //var_dump($scoreMatrix);
        $maxRatioFraction = -1;
        $rowIndex = -1;
        $colIndex = -1;
        $maxCell = null;
        foreach($scoreMatrix as $keyIndex => $keyRow)
        {
            foreach($keyRow as $resIndex => $cell)
            {
                if ($cell->matchRatioFraction > $maxRatioFraction)
                {
                    $maxCell = $cell;
                    $maxRatioFraction = $cell->matchRatioFraction;
                    $rowIndex = $keyIndex;
                    $colIndex = $resIndex;
                }
            }
        }
        // remove max row and col
        unset($scoreMatrix[$rowIndex]);
        foreach ($scoreMatrix as $keyIndex => $keyRow)
        {
            unset($scoreMatrix[$keyIndex][$colIndex]);
        }
        return array($rowIndex, $colIndex, $maxCell);
    }
    /*
    protected function gradeResponseOfGroup($blankGroup, $responses)
    {
        //var_dump($blankGroup);
        //var_dump($responses);
        $dupResponses = $responses;
        $firstResponse = array_shift($dupResponses);
        // find the matches key to first answer
        $maxRatio = 0;
        $maxMatchKey = null;
        $firstKeyIndex = 0;
        $indexInBlankGroup = 0;
        foreach ($blankGroup as $i => $keyIndex)
        {
            $keys = $this->answerKeyMap[$keyIndex];
            list($matchKey, $matchRatio) = $this->getMatchKeyOfResponse($firstResponse, $keys);
            if ($matchRatio > $maxRatio)
            {
                $maxRatio = $matchRatio;
                $maxMatchKey = $matchKey;
                $firstKeyIndex = $keyIndex;
                $indexInBlankGroup = $i;
            }
        }

        // save result to current blanks
        $firstBlankIndex = $blankGroup[$indexInBlankGroup];
        //echo 'first blank index', $firstBlankIndex;
        $blank = $this->blanks[$firstBlankIndex];
        //var_dump($blank);
        $blank->matchAnswerKey = $maxMatchKey;
        $blank->matchRatio = $maxRatio;
        if (isset($maxMatchKey))
            $blank->fraction = $maxMatchKey->fraction * $maxRatio;
        else
            $blank->fraction = 0;
        $blank->score = $this->getBlankScore($firstKeyIndex) * $maxRatio;
        // after first answer is handled, process the remaining ones
        $remainingGroup = $blankGroup;
        array_splice($remainingGroup, $indexInBlankGroup, 1);
        if (!empty($remainingGroup))
            $this->gradeResponseOfGroup($remainingGroup, $dupResponses);
    }
    */

    /**
     * Returns most matched key and max matching ratio to $answer.
     * @param $responseItem
     * @param $answerKeys
     */
    protected function getMatchKeyOfResponse($responseItem, $answerKeys)
    {
        $matchKey = null;
        $maxRatio = 0;
        $maxRatioFraction = 0;
        foreach ($answerKeys as $key)
        {
            $ratio = $this->calcMatchingRatio($responseItem, $key);
            $ratioFraction = $ratio * $key->fraction;
            if ($ratio > $maxRatio)
            {
                $maxRatio = $ratio;
                $matchKey = $key;
                $maxRatioFraction = $ratioFraction;
            }
            else if ($ratio == $maxRatio)
            {
                if ($ratioFraction > $maxRatioFraction)
                {
                    $maxRatio = $ratio;
                    $matchKey = $key;
                    $maxRatioFraction = $ratioFraction;
                }
            }
        }
        return array($matchKey, $maxRatio, $maxRatioFraction);
    }
    /**
     * Compare answer and key, returns a value from 0 to 1.
     * 1 means key and answer are same, 0 means totally different.
     * Intermedium value can also be returned.
     * Descendants can override this method.
     * @param $responseItem
     * @param $key
     */
    protected function calcMatchingRatio($responseItem, $key)
    {
        /*
        var_dump($answer);
        var_dump($key);
        */
        if (strcmp($responseItem, $key->answer) == 0)
            return 1;
        else
            return 0;
    }

    public function getBlankDefaultMark($blankIndex)
    {
        $blank = $this->blanks[$blankIndex];
        return $this->defaultmark * ($blank->scoreRatio / $this->scoreRatioSum);
    }

    /**
     * @param int $key stem number
     * @return string the question-type variable name.
     */
    protected function getFieldName($key) {
        return 'answer' . $key;
    }

    //// overwrite methods //////////////////////////////////////
    public function get_expected_data() {
        $result = array();
        for ($i = 0; $i < $this->blankCount; ++$i)
        {
            $result[$this->getFieldName($i)] = PARAM_RAW_TRIMMED;
        }
        return $result;
    }

    public function get_correct_response() {
        $correctSet = $this->getCorrectAnswerSet();
        $response = array();
        for ($i = 0; $i < $this->blankCount; ++$i)
        {
            $response[$this->getFieldName($i)] = $correctSet[$i]->answer;
        }
        return $response;
    }

    public function is_complete_response(array $response)
    {
        for ($i = 0; $i < $this->blankCount; ++$i)
        {
            if (empty($response['answer' . strval($i)]))
                return false;
        }
        return true;
    }

    public function is_gradable_response(array $response) {
        for ($i = 0; $i < $this->blankCount; ++$i)
        {
            if (!empty($response['answer' . strval($i)]))
                return true;
        }
        return false;
    }

    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleaseananswerallparts', 'qtype_kekule_multianswer');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        //return false;
        $prevAns = $this->getResponseAnswerData($prevresponse);
        $newAns = $this->getResponseAnswerData($newresponse);
        if (empty($prevAns) || empty($newAns))
            return false;

        function compareObj($a, $b)
        {
            return ($a == $b)? 0: -1;
        }

        $diff = array_udiff($prevAns, $newAns, 'compareObj');
        return empty($diff);
        /*
        if (!isset($prevAns) || !isset($newAns))
            return 0;
        else
            return $this->calcMatchingRatio($prevAns, $newAns) == 1;
        */
    }

    public function grade_response(array $response) {

        //var_dump($this->subGroups);
        //die();
        //$answers = $response['answer'];
        /*
        list($right, $total) = $this->get_num_parts_right($response);
        $fraction = $right / $total;
        */

        // got answer to index map
        $currAnswers = array();
        for ($i = 0; $i < $this->blankCount; ++$i)
        {
            $currAnswers[$i] = $response[$this->getFieldName($i)];
        }
        // grade answers according to blank groups
        foreach ($this->subGroups as $key => $group)
        {
            // extract answers of current group
            $groupResponses = array();
            foreach ($group as $key => $blankIndex)
            {
                if (array_key_exists($blankIndex, $currAnswers))
                    $groupResponses[] = $currAnswers[$blankIndex];
                else
                    $groupResponses[] = null;  // empty answer
            }
            $this->gradeResponseOfGroup($group, $groupResponses);
        }

        // sum up
        $scoreSum = 0;
        foreach($this->blanks as $blank)
        {
            $scoreSum += $blank->score;
        }
        $fraction = $scoreSum / $this->defaultmark;
        //$fraction = 1;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function compute_final_grade($responses, $totaltries) {
        /*
        $totalstemscore = 0;
        foreach ($this->stemorder as $key => $stemid) {
            $fieldname = $this->field($key);

            $lastwrongindex = -1;
            $finallyright = false;
            foreach ($responses as $i => $response) {
                if (!array_key_exists($fieldname, $response) || !$response[$fieldname] ||
                    $this->choiceorder[$response[$fieldname]] != $this->right[$stemid]) {
                    $lastwrongindex = $i;
                    $finallyright = false;
                } else {
                    $finallyright = true;
                }
            }

            if ($finallyright) {
                $totalstemscore += max(0, 1 - ($lastwrongindex + 1) * $this->penalty);
            }
        }

        return $totalstemscore / count($this->stemorder);
        */

        $maxFraction = 0;
        foreach($responses as $response)
        {
            $fraction = $this->grade_response($response);
            if ($fraction > $maxFraction)
                $maxFraction = $fraction;
        }
        return $maxFraction * $this->defaultmark;
    }

    public function summarise_response(array $response) {
        $answers = $this->getResponseAnswerData($response);
        //var_dump($answers);
        $result = array();
        foreach($answers as $ans)
        {
            if (is_object($ans))
                $result[] = $ans->answer;
            else  // $ans is string
                $resut[] = $ans;
        }
        return implode('; ', $result);
    }
}