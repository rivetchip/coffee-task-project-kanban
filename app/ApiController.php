<?php

namespace App;

use Slim\Http\Interfaces\RequestInterface as Request;
use Slim\Http\Interfaces\ResponseInterface as Response;

use App\Models\UserModel;
use App\Models\ProjectsModel;
use App\Models\TasksModel;
use App\Models\CategoryModel;
use App\Models\FileModel;

use RuntimeException;
use DateTime;


/**
 * Api Controller
 * Used in Ajax-based operations
 *
 * @package  CoffeeTask
 * @version  v.1 (12/02/2018)
 * @author   rivetchip
 */
class ApiController extends AbstractController {

    /**
     * Api error message
     *
     * @param string $code
     * @return string
     */
    protected function apiError( $code )
    {
        $messages = [
            'CannotAction' => 'You don\'t have rights to perform this action',

            'UserNotLogged' => 'User not logged',
            'ProjectNotExist' => 'Project does not exist',
            'ProjectNotCreated' => 'Project cannot be created',
            'TaskNotExist' => 'Task does not exist',
            'TaskNotCreated' => 'Task cannot be created',
            'TaskNotDeleted' => 'Task cannot be deleted',
            'TaskNotUpdated' => 'Task cannot be updated',
            'CategoryNotUpdated' => 'Category cannot be updated',
            'CategoryNotDeleted' => 'Category cannot be deleted',
            'CategoryNotCreated' => 'Category cannot be created',
            'FileNotExist' => 'File does not exist',
        ];

        $message = $code;

        if( !empty($messages[$code]) ) {
            $message = $messages[$code];
        }

        return json(['error' => [ 'code' => $code, 'message' => $message ]]);
    }

    /**
     * Heartbeat
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function heartbeat( Request $request, Response $response ){}



//////// PROJECTS

    /**
     * Get all projects
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function projectsList( Request $request, Response $response )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $results = ProjectsModel::getAllByUser($this->userId);

        return json($results);
    }

    /**
     * Get all proples link to the project
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    public function projectPeoples( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('project', 'read', $projectId) ) {
            return $this->apiError('CannotAction');
        }

        $results = ProjectsModel::getPeoples($projectId);
        
        return json($results);
    }

    /**
     * Create a new project
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    public function projectCreate( Request $request, Response $response )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('project', 'create') ) {
            return $this->apiError('CannotAction');
        }


        $pTitle = $request->input('title', '');
        $pDescription = $request->input('description', '');
	
        $pUsers = $request->input('users', '');
        $pUsers = preg_split("/\\r\\n|\\r|\\n/", $pUsers);

        $pUsers = array_unique($pUsers);

        $pManager = $request->input('manager', '');

        $data = [
            'title' => $pTitle,
            'description' => $pDescription,
            'manager' => $pManager,
            'users' => $pUsers,
        ];

        $userId = $this->userId;

        $projectId = ProjectsModel::createNew($userId, $data);

        if( $projectId ) {

            return json(['message'=>'ok', 'projectId' => $projectId]);
        }

        return $this->apiError('ProjectNotCreated');
    }

    /**
     * Update the project
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    /*public function projectUpdate( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('project', 'update', $projectId) ) {
            return $this->apiError('CannotAction');
        }

        // TODO

    }*/

    /**
     * Delete the project
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    public function projectDelete( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('project', 'delete', $projectId) ) {
            return $this->apiError('CannotAction');
        }


        $project = ProjectsModel::find($projectId);

        if( $project ) {

            $project->delete();

            return json(['message'=>'ok']);
        }

        return $this->apiError('ProjectNotExist');
    }


//////// PHOTOS-FOLDER

    /**
     * List all photos grouped by tasks
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    public function photosFolder( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $userId = $this->userId;

        // find project :

        $project = ProjectsModel::find($projectId);

        if( $project ) {

            // check persmission
            if( !$this->canAction('photos-folder', 'read', $projectId) ) {
                return $this->apiError('CannotAction');
            }
            
            $tasks = FileModel::getFilesFolderUnvalidate($projectId, $userId);

            return json($tasks);
        }

        return $this->apiError('ProjectNotExist');
    }

    /**
     * Apply an action to the specified file
     *
     * @param Request $request
     * @param Response $response
     * @param int $fileId
     * @return string
     */
    public function photosFolderAction( Request $request, Response $response, $fileId, $action = null )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $userId = $this->userId;

        // find file cascade :

        $file = FileModel::find($fileId);

        $task = null;
        $project = null;

        if( $file ) {
            $task = TasksModel::find($file->task_id);
        }

        if( $task ) {
            $project = ProjectsModel::find($task->project_id);
        }

        if( $file && $task && $project ) {

            $projectId = $project->id;

            $result = false;

            if( !empty($action) )
            {
                $perm = 'update';

                if( $action == 'delete' )
                {
                    $file->is_deleted = true;
    
                    $file->deleted_at = DatabaseDatetime();

                    $perm = 'delete';//permission action
                }
    
                if( $action == 'validate' )
                {
                    $file->is_validate = true;

                    $perm = 'update';//permission action
                }

                // check persmission
                if( !$this->canAction('photos-folder', $perm, $projectId) ) {
                    return $this->apiError('CannotAction');
                }

                $result = $file->save();
            }

            if( $result )
            {
                return json(['message'=>'ok']);
            }
        }

        return $this->apiError('FileNotExist');
    }



//////// CATEGORIES

    /**
     * List all categories of the project
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    public function categoriesList( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $project = ProjectsModel::find($projectId);
        
        if( $project ) {

            // check persmission
            if( !$this->canAction('category', 'read', $projectId) ) {
                return $this->apiError('CannotAction');
            }

            $permissionEdit = false;

            // check persmission edit category
            if( $this->canAction('category', 'update', $projectId) ) {
                $permissionEdit = true;
            }

            $categories = ProjectsModel::findCategories($projectId);

            return json(['categories' => $categories, 'permissionEdit' => $permissionEdit]);
        }

        return $this->apiError('ProjectNotExist');
    }

    /**
     * Get tasks from a category and a project
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @param int $categoryId
     * @return string
     */
    public function categoriesTasksList( Request $request, Response $response, $projectId, $categoryId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('category', 'read', $projectId) ) {
            return $this->apiError('CannotAction');
        }

        // enable filter

        $filter = $request->query('filter', null);

        // get task

        $userId = $this->userId;

        $assignedTo = $userId;

        $isPermissionSeeAll = false;

        if( $this->canAction('task', 'read_all', $projectId) )
        {
            $assignedTo = null; // no assignee ; see all
            $isPermissionSeeAll = true;
        }

        $results = TasksModel::getAllFromProjectCategoryUser($projectId, $categoryId, $assignedTo, $filter);

        return json(['permission_see_all' => $isPermissionSeeAll, 'tasks' => $results]);
    }

    /**
     * Create a category
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @return string
     */
    public function categoryCreate( Request $request, Response $response, $projectId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('category', 'create', $projectId) ) {
            return $this->apiError('CannotAction');
        }

        $color = $request->input('color', null);

        $title = $request->input('title', '');

        $category = new CategoryModel([
            'project_id' => $projectId,
            'title' => $title,
            'color' => $color,
        ]);

        $categoryId = $category->create();

        if( $categoryId )
        {
            $category = CategoryModel::find($categoryId);

            $attributes = $category->getAttributes();

            $permissionEdit = false;

            // check persmission edit category
            if( $this->canAction('category', 'update', $projectId) ) {
                $permissionEdit = true;
            }

            return json(['message'=>'ok', 'category' => $attributes, 'permissionEdit' => $permissionEdit]);
        }

        return $this->apiError('CategoryNotCreated');
    }

    /**
     * Update a category
     *
     * @param Request $request
     * @param Response $response
     * @param int $categoryId
     * @return string
     */
    public function categoryUpdate( Request $request, Response $response, $categoryId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');


        $category = CategoryModel::find($categoryId);

        if( $category )
        {
            $projectId = $category->project_id;

            // check persmission
            if( !$this->canAction('category', 'update', $projectId, $categoryId) ) {
                return $this->apiError('CannotAction');
            }

            // update model

            $title = $request->input('title', '');

            $category->title = $title;

            $result = $category->save();

            if( $result )
            {
                return json(['message'=>'ok']);
            }
        }

        return $this->apiError('CategoryNotUpdated');
    }

    /**
     * Delete a category
     *
     * @param Request $request
     * @param Response $response
     * @param int $categoryId
     * @return string
     */
    public function categoryDelete( Request $request, Response $response, $categoryId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');


        $category = CategoryModel::find($categoryId);
        
        if( $category )
        {
            $projectId = $category->project_id;

            // check persmission
            if( !$this->canAction('category', 'delete', $projectId, $categoryId) ) {
                return $this->apiError('CannotAction');
            }

            $result = $category->delete();

            if( $result )
            {
                return json(['message'=>'ok']);
            }
        }

        return $this->apiError('CategoryNotDeleted');
    }


//////// TASKS


    /**
     * Get a task
     *
     * @param Request $request
     * @param Response $response
     * @param int $taskId
     * @return string
     */
    public function taskGet( Request $request, Response $response, $taskId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $userId = $this->userId;

        $task = TasksModel::getSingle($taskId);

        if( !empty($task) )
        {
            $projectId = $task['project_id'];
            $categoryId = $task['category_id'];

            // check persmission
            if( !$this->canAction('task', 'read', $projectId, $taskId) ) {
                return $this->apiError('CannotAction');
            }

            return json($task);
        }


        return $this->apiError('TaskNotExist');
    }


    /**
     * Create a task
     *
     * @param Request $request
     * @param Response $response
     * @param int $projectId
     * @param int $categoryId
     * @return string
     */
    public function taskCreate( Request $request, Response $response, $projectId, $categoryId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        // check persmission
        if( !$this->canAction('task', 'create', $projectId) ) {
            return $this->apiError('CannotAction');
        }

        $title = $request->input('title', '');
        $description = $request->input('description', '');

        $assignedTo = $request->input('people');

        if( empty($assignedTo) ) // if not assigned (0) set to self
        {
            $assignedTo = $this->userId;
        }

        $dateEndAt = $request->input('end-at');

        if( empty($dateEndAt) ) // current date + 1week
        {
            //$date = (new DateTime())->modify('+1 week');
            //$dateEndAt = $date->format('Y-m-d H:i:s');
            $dateEndAt = null;
        }

        $task = new TasksModel([
            'project_id' => $projectId,
            'category_id' => $categoryId,
            'assigned_to' => $assignedTo,

            'title' => $title,
            'description' => $description,
            'end_at' => $dateEndAt,
        ]);

        $taskId = $task->create();

        if( $taskId )
        {
            // upload files to validate by the admin later :

            $files = $request->input('files');

            $attachmentFolder = getcwd() . '/uploads/files/';

            if( !is_writable($attachmentFolder) )
            {
                throw new RuntimeException('Folder is not writable');
            }

            // list files

            $uploadsNumber = 0;

            if( !empty($files) && is_array($files) )
            {
                foreach( $files as $key => $file )
                {
                    // save

                    $fileModel = new FileModel([
                        'task_id' => $taskId,
                        'filename' => $file['name'] ?: '(unknown)',
                        'size' => $file['size'] ?: 0,
                        'mimetype' => $file['type'] ?: 'none',
                        //'lastModified'
                    ]);

                    $filename = $fileModel->create();

                    $blob = base64_decode($file['blob'] ?: null);


                    $handle = fopen($attachmentFolder.$filename, 'w');

                    if( !empty($handle) )
                    {
                        $result = fwrite($handle, $blob, strlen($blob));

                        fclose($handle);

                        $uploadsNumber++;
                    }
                }
            }

            $isPermissionSee = false;

            // check persmission
            if( $this->canAction('task', 'read', $projectId, $taskId) )
            {
                $isPermissionSee = true;
            }

            return json(['message'=>'ok', 'taskId' => $taskId, 'uploadsNumber' => $uploadsNumber, 'xisPermissionSee' => $isPermissionSee]);
        }

        return $this->apiError('TaskNotCreated');
    }

    /**
     * Update a task
     *
     * @param Request $request
     * @param Response $response
     * @param int $taskId
     * @return string
     */
    /*public function taskUpdate( Request $request, Response $response, $taskId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');

        $userId = $this->userId;
        
        $task = TasksModel::find($taskId);

        if( $task )
        {
            $projectId = $task->project_id;

            // check persmission
            if( !$this->canAction('task', 'update', $projectId, $taskId) ) {
                return $this->apiError('CannotAction');
            }

            // TODO

            $dateEndAt = $request->input('end-at');


        }

        return $this->apiError('TaskNotUpdated');
    }*/

    /**
     * Delete a task
     *
     * @param Request $request
     * @param Response $response
     * @param int $taskId
     * @return string
     */
    public function taskDelete( Request $request, Response $response, $taskId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');
        

        $userId = $this->userId;

        $task = TasksModel::find($taskId);

        if( $task )
        {
            $projectId = $task->project_id;

            // check persmission
            if( !$this->canAction('task', 'delete', $projectId, $taskId) ) {
                return $this->apiError('CannotAction');
            }

            // delete db

            $result = $task->delete();

            if( $result )
            {
                return json(['message'=>'ok']);
            }
        }
        
        return $this->apiError('TaskNotDeleted');
    }

    /**
     * Complete a task
     *
     * @param Request $request
     * @param Response $response
     * @param int $taskId
     * @return string
     */
    public function taskComplete( Request $request, Response $response, $taskId )
    {
        if( !$this->isLogged ) return $this->apiError('UserNotLogged');


        $userId = $this->userId;

        $task = TasksModel::find($taskId);

        if( $task )
        {
            $projectId = $task->project_id;
            $categoryId = $task->category_id;

            // check persmission
            if( !$this->canAction('task', 'update', $projectId, $taskId) ) {
                return $this->apiError('CannotAction');
            }

            // delete db

            $result = $task->complete();

            // send mail to the assignee :

            $sendMailSuccess = false;

            $assignedUser = UserModel::find($task->assigned_to);

            if( $assignedUser )
            {
                $project = ProjectsModel::find($projectId);
                $category = CategoryModel::find($categoryId);

                //$logo_blob = 'data:image/png;base64,'.base64_encode(file_get_contents('img/logo-header.png'));
                $logo_blob = 'https://i.imgur.com/WbuXEt5.png'; // temporary GMAIl!!

                $mailBody = render('mails/task-complete', [
                    'project' => $project,
                    'category' => $category,
                    'task' => $task,
                    'logo_blob' => $logo_blob
                ]);

                $xmailOptions = [
                    'subject' => 'La tâche "'.($task->title).'" vient d\'être complétée!',
                    'address' => [$assignedUser->email, $assignedUser->fullname],
                    'body' => $mailBody,
                    //'body-txt' => strip_tags($mailBody, '<br><br/>')
                ];

                $mail = xmail($xmailOptions);

                if( $mail )
                {
                    $sendMailSuccess = true;
                }
            }

            if( $result )
            {
                return json(['message'=>'ok', 'send_mail' => $sendMailSuccess]);
            }
        }
        
        return $this->apiError('TaskNotUpdated');
    }


}
