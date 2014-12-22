<?php

class CbtsController extends BaseController {

        public function getIndex()
        {
		$data = Cbt::curuser()->orderBy('date', 'DESC')->get();

		if ($data)
		{
                        return View::make('cbts.index')->with('cbts', $data);
		}

		return Redirect::action('DashboardController@getIndex')
			->with('alert-danger', 'No data.');
        }

        public function getCreate()
        {
                $feelings_list = array(0 => 'Please select...') +
                        Feeling::curuser()->orderBy('name', 'ASC')->lists('name', 'id');

                $symptoms_list = array(0 => 'Please select...') +
                        Symptom::curuser()->orderBy('name', 'ASC')->lists('name', 'id');

                return View::make('cbts.create')
                        ->with('feelings_list', $feelings_list)
                        ->with('symptoms_list', $symptoms_list);
        }

        public function postCreate()
        {
                $input = Input::all();
                $temp1 = date_create_from_format('d M Y H:i A', $input['date']);
                $input['date'] = date_format($temp1, 'Y-m-d H:i:s');

                $rules = array(
                        'date' => 'required|date',
                        'situation' => 'required|min:3'
                );

                $validator = Validator::make($input, $rules);

                if ($validator->fails())
                {
                        return Redirect::back()->withInput()->withErrors($validator);
                } else {

                        /* Create a CBT entry */
                        $cbt_data = array(
                                'date' => $input['date'],
                                'situation' => $input['situation']
                        );
                        $cbt = Cbt::create($cbt_data);
			if (!$cbt)
			{
			        return Redirect::back()->withInput()
                                        ->with('alert-danger', 'Failed to add CBT entry.');
			}

                        /* Add thoughts */
                        $thoughts = array();
                        foreach ($input['thoughts'] as $row)
                        {
                                $row = trim($row);
                                if (strlen($row) > 0)
                                {
                                        $thoughts[] = array(
                                                'thought' => $row,
                                                'is_challenged' => 0,
                                                'dispute' => '',
                                                'balanced_thoughts' => '',
                                        );
                                }
                        }

                        foreach ($thoughts as $data)
                        {
                                $cbtThought = new CbtThought($data);
                                $cbtThought->cbt()->associate($cbt);
                                if (!$cbtThought->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add thoughts.');
                                }
                        }

                        /* Add feelings */
                        $feelings = array();
                        $feelingsintensity = $input['feelingsintensity'];
                        foreach ($input['feelings'] as $row_id => $row)
                        {
                                if (!empty($row))
                                {
                                        $feelings[] = array(
                                                'feeling_id' => $row,
                                                'percent' => $feelingsintensity[$row_id],
                                                'when' => 'B',
                                        );
                                }
                        }

                        foreach ($feelings as $data)
                        {
                                $cbt_feeling = new CbtFeeling($data);
                                $cbt_feeling->cbt()->associate($cbt);
                                if (!$cbt_feeling->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add feelings.');
                                }
                        }

                        /* Add symptom */
                        $symptoms = array();
                        $symptomsintensity = $input['symptomsintensity'];
                        foreach ($input['symptoms'] as $row_id => $row)
                        {
                                if (!empty($row))
                                {
                                        $symptoms[] = array(
                                                'symptom_id' => $row,
                                                'percent' => $symptomsintensity[$row_id],
                                                'when' => 'B',
                                        );
                                }
                        }

                        foreach ($symptoms as $data)
                        {
                                $cbt_symptom = new CbtSymptom($data);
                                $cbt_symptom->cbt()->associate($cbt);
                                if (!$cbt_symptom->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add physical symptom.');
                                }
                        }

                        /* Add behaviours */
                        $behaviours = array();
                        foreach ($input['behaviours'] as $row)
                        {
                                $row = trim($row);
                                if (strlen($row) > 0)
                                {
                                        $behaviours[] = array(
                                                'behaviour' => $row,
                                                'when' => 'B',
                                        );
                                }
                        }

                        foreach ($behaviours as $data)
                        {
                                $cbt_behaviour = new CbtBehaviour($data);
                                $cbt_behaviour->cbt()->associate($cbt);
                                if (!$cbt_behaviour->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add behaviours.');
                                }
                        }

                        /* Everything ok */
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-success', 'CBT exercise added successfully.');
                }
        }

        public function getPostdispute($id)
        {
		$cbt = Cbt::find($id);
                if (!$cbt)
                {
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-danger', 'Cbt exercise not found.');
                }

                if ($cbt['user_id'] != Auth::id())
                {
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-danger', 'Invalid access.');
                }

                $feelings_list = array(0 => 'Please select...') +
                        Feeling::curuser()->orderBy('name', 'ASC')->lists('name', 'id');

                $symptoms_list = array(0 => 'Please select...') +
                        Symptom::curuser()->orderBy('name', 'ASC')->lists('name', 'id');

                return View::make('cbts.postdispute')
                        ->with('feelings_list', $feelings_list)
                        ->with('symptoms_list', $symptoms_list);
        }

        public function postPostdispute($id)
        {
		$cbt = Cbt::find($id);
                if (!$cbt)
                {
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-danger', 'Cbt exercise not found.');
                }

                if ($cbt['user_id'] != Auth::id())
                {
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-danger', 'Invalid access.');
                }

                $input = Input::all();

                $rules = array(
                );

                $validator = Validator::make($input, $rules);

                if ($validator->fails())
                {
                        return Redirect::back()->withInput()->withErrors($validator);
                } else {

                        /* Add feelings */
                        $feelings = array();
                        $feelingsintensity = $input['feelingsintensity'];
                        foreach ($input['feelings'] as $row_id => $row)
                        {
                                if (!empty($row))
                                {
                                        $feelings[] = array(
                                                'feeling_id' => $row,
                                                'percent' => $feelingsintensity[$row_id],
                                                'when' => 'A',
                                        );
                                }
                        }

                        foreach ($feelings as $data)
                        {
                                $cbt_feeling = new CbtFeeling($data);
                                $cbt_feeling->cbt()->associate($cbt);
                                if (!$cbt_feeling->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add feelings.');
                                }
                        }

                        /* Add symptoms */
                        $symptoms = array();
                        $symptomsintensity = $input['symptomsintensity'];
                        foreach ($input['symptoms'] as $row_id => $row)
                        {
                                if (!empty($row))
                                {
                                        $symptoms[] = array(
                                                'symptom_id' => $row,
                                                'percent' => $symptomsintensity[$row_id],
                                                'when' => 'A',
                                        );
                                }
                        }

                        foreach ($symptoms as $data)
                        {
                                $cbt_symptom = new CbtSymptom($data);
                                $cbt_symptom->cbt()->associate($cbt);
                                if (!$cbt_symptom->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add physical symptoms.');
                                }
                        }

                        /* Add behaviours */
                        $behaviours = array();
                        foreach ($input['behaviours'] as $row)
                        {
                                $row = trim($row);
                                if (strlen($row) > 0)
                                {
                                        $behaviours[] = array(
                                                'behaviour' => $row,
                                                'when' => 'A',
                                        );
                                }
                        }

                        foreach ($behaviours as $data)
                        {
                                $cbt_behaviour = new CbtBehaviour($data);
                                $cbt_behaviour->cbt()->associate($cbt);
                                if (!$cbt_behaviour->save())
                                {
			                return Redirect::back()->withInput()
                                                ->with('alert-danger', 'Failed to add behaviours.');
                                }
                        }

                        /* Everything ok */
                        return Redirect::action('CbtsController@getIndex')
                                ->with('alert-success', 'CBT exercise completed successfully.');
                }
        }

}
