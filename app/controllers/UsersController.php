<?php
/**
 * The MIT License (MIT)
 *
 * WebCBT - Web based Cognitive Behavioral Therapy tool
 *
 * http://webcbt.github.io
 *
 * Copyright (c) 2014 Prashant Shah <pshah.webcbt@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class UsersController extends BaseController {

	public function getLogin()
	{
		return View::make('users.login');
	}

	public function postLogin()
	{
		$input = Input::all();

		$login_data = array(
			'username' => $input['username'],
			'password' => $input['password'],
			'status' => 1
		);

		if (Auth::attempt($login_data))
		{
			/* Update last login datetime */
			$user = User::find(Auth::id());
			$user->last_login = date('Y-m-d H:i:s', time());
			$user->reset_password_key = NULL;
			$user->reset_password_date = NULL;
			$user->save();

			return Redirect::intended('cbts')
				->with('alert-success', 'Hi ' . Auth::user()->fullname . ', welcome back !');
		}

		return Redirect::action('UsersController@getLogin')
			->with('alert-danger', 'Login failed.');
	}

	public function getLogout()
	{
		Auth::logout();
		Session::flush();

		return Redirect::action('UsersController@getLogin')
                        ->with('alert-success', 'User logged out.');
	}

	public function getRegister()
	{
		return View::make('users.register');
	}

	public function postRegister()
	{
                $input = Input::all();

                $rules = array(
                        'username' => 'required|unique:users,username',
			'email' => 'required|email|unique:users,email',
			'password' => 'required|min:3',
                );

                $validator = Validator::make($input, $rules);

                if ($validator->fails())
                {
                        return Redirect::back()->withInput()->withErrors($validator);
                }
		else
		{

                        /* Create a symptom */
                        $user_data = array(
                                'username' => $input['username'],
				'password' => Hash::make($input['password']),
				'fullname' => '',
				'email' => $input['email'],
				'dateformat' => 'd-M-Y|dd-M-yy',
				'timezone' => 'UTC',
				'status' => 1,
				'verification_key' =>
					substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 20),
				'email_verified' => 0,
				'admin_verified' => 0,
				'retry_count' => 0,
				'reset_password_key' => NULL,
				'reset_password_date' => NULL,
                        );
                        $user = User::create($user_data);
			if (!$user)
			{
			        return Redirect::back()->withInput()
                                        ->with('alert-danger', 'Failed to create user.');
			}

			$this->initDB($user->id);

			/* Send email on successful registration */
			try
			{
				Mail::send('emails.users.register', Input::all(), function($message) {
					$message
						->to(Input::get('email'), Input::get('username'))
						->subject('Welcome to ' . Config::get('webcbt.SITE_NAME') .
							' - Your account has been created'
						);
				});
			}
			catch (Exception $e)
			{
	                        return Redirect::action('UsersController@getLogin')
					->with('alert-success', 'User created. Please login below.')
					->with('alert-danger', 'Error sending email.');
			}

                        return Redirect::action('UsersController@getLogin')
                                ->with('alert-success', 'User created. Please login below.');
                }
	}

	public function getForgot()
	{
		return View::make('users.forgot');
	}

	public function postForgot()
	{
		$input = Input::all();

		if (!empty($input['userinput']))
		{
			$reset_password = false;

			$user = User::where('username', '=', $input['userinput'])->first();
			if ($user)
			{
				$reset_password = true;
			}
			else
			{
				$user = User::where('email', '=', $input['userinput'])->first();
				if ($user)
				{
					$reset_password = true;
				}
			}

			if ($reset_password == true)
			{
				$reset_key = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 20);

				$user->reset_password_key = sha1($reset_key);
				$user->reset_password_date = date('Y-m-d H:i:s', time());

				if ($user->save())
				{
					/* Send email */
					try
					{
						$param = array(
							'key' => $user->reset_password_key,
							'username' => $user->username,
						);
						Mail::send('emails.users.forgot', $param, function($message) use ($user) {
							$message
								->to($user->email, $user->username)
								->subject('Reset password');
						});
					}
					catch (Exception $e)
					{
						/* Reset everything */
						$user->reset_password_key = NULL;
						$user->reset_password_date = NULL;
						$user->save();

			                        return Redirect::back()->withInput()
							->with('alert-danger', 'Error sending email.');
					}

					return Redirect::action('UsersController@getLogin')
						->with('alert-success', 'Password resetted. Please check your email.');
				}

				return Redirect::action('UsersController@getLogin')
					->with('alert-danger', 'Failed to reset password.');

			}

			return Redirect::back()->withInput()
                                ->with('alert-danger', 'User does not exists.');
		}
		return View::make('users.forgot');
	}

	public function getResetpass()
	{
		$key = Input::get('k');
		$username = Input::get('u');

		if (empty($key) || strlen($key) != 40) {
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'Failed to reset password.');
		}

		if (empty($username)) {
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'Failed to reset password.');
		}

		$users = User::where('reset_password_key', '=', $key)
			->where('username', '=', $username)
			->get();

		if ($users->count() < 1)
		{
			$temp_user = User::where('username', '=', $username)->first();
			if ($temp_user)
			{
				$temp_user->reset_password_key = NULL;
				$temp_user->reset_password_date = NULL;
				$temp_user->save();

				return Redirect::action('UsersController@getForgot')
					->with('alert-danger', 'Verification failed. Please restart the forgot password process again.');
			}
			return Redirect::action('UsersController@getForgotpass')
				->with('alert-danger', 'Verification failed.');
		}

		if ($users->count() > 1)
		{
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'More than 1 user have the same key. Please redo the reset process.');
		}

		$cur_user = $users->first();

                $reset_date = date_create_from_format('Y-m-d H:i:s', $cur_user->reset_password_date);
		if (!$reset_date)
		{
			return Redirect::action('UsersController@getForgot')
				->with('alert-danger', 'Internal error has occured. Please restart the forgot password process again.');
		}
                $todays_date = date_create('now');
		$diff_ts = $todays_date->getTimestamp() - $reset_date->getTimestamp();

		/* Verification time should be within 24 hours (60 * 60 * 24) */
		if ($diff_ts < 0 || $diff_ts > (60 * 60 * 24))
		{
			$cur_user->reset_password_key = NULL;
			$cur_user->reset_password_date = NULL;
			$cur_user->save();

			return Redirect::action('UsersController@getForgot')
				->with('alert-danger', 'Verification time expired. Please restart the forgot password process again.');
		}

		return View::make('users.resetpass')
			->with('k', $key)
			->with('u', $username);
	}

	public function postResetpass()
	{
		/* Initial code same as getResetpass() */

		$key = Input::get('k');
		$username = Input::get('u');

		if (empty($key) || strlen($key) != 40) {
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'Failed to reset password.');
		}

		if (empty($username)) {
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'Failed to reset password.');
		}

		$users = User::where('reset_password_key', '=', $key)
			->where('username', '=', $username)
			->get();

		if ($users->count() < 1)
		{
			$temp_user = User::where('username', '=', $username)->first();
			if ($temp_user)
			{
				$temp_user->reset_password_key = NULL;
				$temp_user->reset_password_date = NULL;
				$temp_user->save();

				return Redirect::action('UsersController@getForgot')
					->with('alert-danger', 'Verification failed. Please restart the forgot password process again.');
			}
			return Redirect::action('UsersController@getForgotpass')
				->with('alert-danger', 'Verification failed.');
		}

		if ($users->count() > 1)
		{
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'More than 1 user have the same key. Please redo the reset process.');
		}

		$cur_user = $users->first();

                $reset_date = date_create_from_format('Y-m-d H:i:s', $cur_user->reset_password_date);
		if (!$reset_date)
		{
			return Redirect::action('UsersController@getForgot')
				->with('alert-danger', 'Internal error has occured. Please restart the forgot password process again.');
		}
                $todays_date = date_create('now');
		$diff_ts = $todays_date->getTimestamp() - $reset_date->getTimestamp();

		/* Verification time should be within 24 hours (60 * 60 * 24) */
		if ($diff_ts < 0 || $diff_ts > (60 * 60 * 24))
		{
			$cur_user->reset_password_key = NULL;
			$cur_user->reset_password_date = NULL;
			$cur_user->save();

			return Redirect::action('UsersController@getForgot')
				->with('alert-danger', 'Verification time expired. Please restart the forgot password process again.');
		}

		/* Reset password */
		$cur_user->password = Hash::make(Input::get('newpassword'));
		$cur_user->reset_password_key = NULL;
		$cur_user->reset_password_date = NULL;

		if ($cur_user->save()) {
			return Redirect::action('UsersController@getLogin')
				->with('alert-success', 'Password updated. Please login again.');
		}
		else
		{
			return Redirect::action('UsersController@getLogin')
				->with('alert-danger', 'Failed to update password.');
		}
	}

	public function getVerify($username, $key = '')
	{
		if (empty($key))
		{
			return Redirect::to('users/login')
				->with('alert-danger', 'User verification failed.');
		}

		$user = User::where('username', '=', $username)
			->where('verification_key', '=', $key)->first();

		if ($user)
		{
			$user->email_verified = 1;
			$user->verification_key = '';

			if ($user->save())
			{
				return Redirect::to('users/login')
					->with('alert-success', 'User verification successful. Please login below.');
			}
			else
			{
				return Redirect::to('users/login')
					->with('alert-danger', 'User verification failed.');
			}

		}
		else
		{
		        return Redirect::to('users/login')
                                ->with('alert-danger', 'User verification failed.');
		}
	}

	public function getProfile()
	{
		$user = Auth::user();

		$timezone_options = timezone_list();

		$dob = '';
                $temp = date_create_from_format(
			'Y-m-d', $user->dob
		);
		if ($temp)
		{
			$dob = date_format(
				$temp, explode('|', $user->dateformat)[0]
			);
		}

		return View::make('users.profile')
			->with('dob', $dob)
			->with('timezone_options', $timezone_options)
			->with('user', $user);
	}

	public function getEditprofile()
	{
		$user = Auth::user();

		$timezone_options = array('' => 'Please select...') + timezone_list();

		$gender_options = array(
			'' => 'Please select...',
			'M' => 'Male',
			'F' => 'Female',
			'U' => 'Undisclosed',
		);

		$dateformat_options = array(
	                '' => 'Please select...',
	                'd-M-Y|dd-M-yy' => 'Day-Month-Year',
	                'M-d-Y|M-dd-yy' => 'Month-Day-Year',
	                'Y-M-d|yy-M-dd' => 'Year-Month-Day',
		);

		$dob = '';
                $temp = date_create_from_format(
			'Y-m-d', $user->dob
		);
		if ($temp)
		{
			$dob = date_format(
				$temp, explode('|', $user->dateformat)[0]
			);
		}

		return View::make('users.editprofile')
			->with('dob', $dob)
			->with('timezone_options', $timezone_options)
			->with('gender_options', $gender_options)
			->with('dateformat_options', $dateformat_options)
			->with('user', $user);
	}

	public function postEditprofile()
	{
		$user = Auth::user();

                $input = Input::all();

		$php_dateformat = explode('|', $input['dateformat'])[0];
                $temp = date_create_from_format($php_dateformat, $input['dob']);
		if (!$temp)
		{
	                return Redirect::back()->withInput()
                                ->with('alert-danger', 'Invalid date of birth.');
		}

                $rules = array(
			'fullname' => 'required',
			'email' => 'required|email|unique:users,email,'.Auth::user()->id,
			'dob' => 'required|date',
			'gender' => 'required|in:M,F,U',
			'dateformat' => 'required',
			'timezone' => 'required',
                );

                $validator = Validator::make($input, $rules);

                if ($validator->fails())
                {
                        return Redirect::back()->withInput()->withErrors($validator);
                }
		else
		{

                        /* Update user */
                        $user->fullname = $input['fullname'];
                        $user->email = $input['email'];
			$user->dob = date_format($temp, 'Y-m-d');
                        $user->gender = $input['gender'];
			$user->dateformat = $input['dateformat'];
			$user->timezone = $input['timezone'];

                        if (!$user->save())
                        {
		                return Redirect::back()->withInput()
                                        ->with('alert-danger', 'Failed to update profile.');
                        }

                        return Redirect::action('UsersController@getProfile')
                                ->with('alert-success', 'Profile updated.');

		}
	}

	public function getChangepass()
	{
		return View::make('users.changepass');
	}

	public function postChangepass()
	{
                $input = Input::all();

		$user = User::where('id', '=', Auth::user()->id)->first();

		if (!$user)
		{
                        return Redirect::action('UsersController@getProfile')
                                ->with('alert-danger', 'Invalid user.');
		}

		if (!Hash::check($input['oldpassword'], $user->password))
		{
	                return Redirect::back()->withInput()
                                ->with('alert-danger', 'Old password does not match.');
		}

		$user->password = Hash::make($input['newpassword']);

                if (!$user->save())
                {
	                return Redirect::back()->withInput()
                                ->with('alert-danger', 'Failed to update password.');
                }

                return Redirect::action('UsersController@getProfile')
                        ->with('alert-success', 'Password updated.');
	}

	/**
	 * This function initializes data for each user
	 */
	private function initDB($user_id)
	{
		$feelings_data = array(
			/* Positive feelings */
			array('name' => 'Calm', 'type' => '1'),
			array('name' => 'Confident', 'type' => '1'),
			array('name' => 'Content', 'type' => '1'),
			array('name' => 'Delighted', 'type' => '1'),
			array('name' => 'Encouraged', 'type' => '1'),
			array('name' => 'Energetic', 'type' => '1'),
			array('name' => 'Excited', 'type' => '1'),
			array('name' => 'Happy', 'type' => '1'),
			array('name' => 'Important', 'type' => '1'),
			array('name' => 'Loved', 'type' => '1'),
			array('name' => 'Peaceful', 'type' => '1'),
			array('name' => 'Pleased', 'type' => '1'),
			array('name' => 'Relaxed', 'type' => '1'),
			array('name' => 'Secure', 'type' => '1'),
			array('name' => 'Special', 'type' => '1'),
			array('name' => 'Strong', 'type' => '1'),

			/* Negative feelings */
			array('name' => 'Afraid', 'type' => '2'),
			array('name' => 'Alone', 'type' => '2'),
			array('name' => 'Angry', 'type' => '2'),
			array('name' => 'Ashamed', 'type' => '2'),
			array('name' => 'Bored', 'type' => '2'),
			array('name' => 'Confused', 'type' => '2'),
			array('name' => 'Disappointed', 'type' => '2'),
			array('name' => 'Empty', 'type' => '2'),
			array('name' => 'Frustrated', 'type' => '2'),
			array('name' => 'Guilty', 'type' => '2'),
			array('name' => 'Hurt', 'type' => '2'),
			array('name' => 'Lonely', 'type' => '2'),
			array('name' => 'Nervous', 'type' => '2'),
			array('name' => 'Powerless', 'type' => '2'),
			array('name' => 'Restless', 'type' => '2'),
			array('name' => 'Sad', 'type' => '2'),
			array('name' => 'Tensed', 'type' => '2'),
			array('name' => 'Tired', 'type' => '2'),
			array('name' => 'Trapped', 'type' => '2'),
		);

		$symptoms_data = array(
			/* Negative symptoms */
			array('name' => 'Chest pain or discomfort', 'type' => '2'),
			array('name' => 'Chills or hot flashes', 'type' => '2'),
			array('name' => 'Dizzy', 'type' => '2'),
			array('name' => 'Fear of dying', 'type' => '2'),
			array('name' => 'Feeling of choking', 'type' => '2'),
			array('name' => 'Heart Palpitation', 'type' => '2'),
			array('name' => 'Losing control/going crazy', 'type' => '2'),
			array('name' => 'Nausea', 'type' => '2'),
			array('name' => 'Numbness', 'type' => '2'),
			array('name' => 'Shortness of breath', 'type' => '2'),
			array('name' => 'Stomach cramps', 'type' => '2'),
			array('name' => 'Sweating', 'type' => '2'),
			array('name' => 'Tingling sensation', 'type' => '2'),
			array('name' => 'Trembling', 'type' => '2'),
		);

		foreach ($feelings_data as $feeling)
		{
			DB::insert('INSERT INTO feelings (user_id, name, type) values (?, ?, ?)',
				array($user_id, $feeling['name'], $feeling['type']));
		}

		foreach ($symptoms_data as $symptom)
		{
			DB::insert('INSERT INTO symptoms (user_id, name, type) values (?, ?, ?)',
				array($user_id, $symptom['name'], $symptom['type']));
		}
	}
}
