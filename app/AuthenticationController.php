<?php

namespace App;

use Slim\Http\Interfaces\RequestInterface as Request;
use Slim\Http\Interfaces\ResponseInterface as Response;

use App\Models\UserModel;



class AuthenticationController extends AbstractController {


    /**
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function login( Request $request, Response $response )
    {
        if( $this->isLogged ) return redirect('/projects'); // logged-in

        return render('login', $this->container);
    }

    /**
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function register( Request $request, Response $response )
    {
        if( $this->isLogged ) return redirect('/projects'); // logged-in

        return render('register', $this->container);
    }

    /**
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function validateLogin( Request $request, Response $response )
    {
        if( $this->isLogged ) return redirect('/projects'); // logged-in


        $email = $request->input('email');
        $password = $request->input('password');

        // create session user
        if( $email && $password )
        {
            $passHash = passhash($password);

            if( $userId = UserModel::getIdByLogin($email, $passHash) )
            {
                // try login
                session('isLogged', true);
                session('userId', $userId);
                
                return redirect('/projects');
            }
            else
            {
                $this->errors = __('login.authentication.failed');
            }
        }
        else
        {
            $this->errors = __('login.authentication.notset');
        }


        return render('login', $this->container);
    }

    /**
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function validateRegister( Request $request, Response $response )
    {
        if( $this->isLogged ) return redirect('/projects'); // logged-in


        $fullname = $request->input('fullname');
        $email = $request->input('email');

        $password1 = $request->input('password1');
        $password2 = $request->input('password2');


        if( empty($fullname) )
        {
            $this->errors = __('register.fullname.notset');
        }

        if( !empty($email) )
        {
            if( $tryUserId = UserModel::getIdByEmail($email) )
            {
                $this->errors = __('register.email.notavailable');
            }
        }
        else
        {
            $this->errors = __('register.email.notset');
        }

        if( !empty($password1) && !empty($password2) )
        {
            if( $password1 != $password2 )
            {
                $this->errors = __('register.passwords.notsame');
            }
        }
        else
        {
            $this->errors = __('register.passwords.notset');
        }

        // no errors : validate

        if( !isset($this->errors) )
        {
            $passHash = passhash($password1);

            $userData = [
                'fullname' => $fullname,
                'email' => $email,
                'password' => $passHash
            ];

            $newUser = new UserModel($userData);

            $userId = $newUser->create();

            // try login
            session('isLogged', true);
            session('userId', $userId);
            
            return redirect('/projects');
        }


        return render('register', $this->container);
    }


    public function logout()
    {
        session_destroy();

        return redirect('/'); //home
    }


}
