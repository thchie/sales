<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use \Auth, \Redirect, \Validator, \Input, \Session, \Response;
use App\Http\Controllers\Controller;
use App\Photo;
use Image;

use \AWS;

class PhotoController extends Controller
{
    protected   $photo;
    public function __construct(Photo $photo) {
            $this->photo = $photo;
        }
    
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
            $this->_data['photos'] = Photo::all();
            return view('photo.index')->with('photos',$this->_data);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
            return view('photo.create')->with('photos',$this->_data);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
            $input = Input::all();
            
            if ($this->photo->isValid($input) && !empty($img)) {
                $mime = $input['file']->getMimeType();
                $fileName = time() . "." . strtolower($input['file']->getClientOriginalExtension());

                $image = Image::make($input['file']->getRealPath());
                $this->upload_s3($image, $fileName, $mime, "resource/Original");
                $image->resize(400, 300);
                $this->upload_s3($image, $fileName, $mime, "resource/Thumbnail");

                Photo::create([
                    'title' => Input::get('title'),
                    'file' => $fileName,
                ]);
                Session::flash('exito', $image);
                return Redirect::route('photo.create');
            } else {
                Session::flash('error', 'Failed');
                return Redirect::back()->withInput()->withErrors($this->photo->messages);
            }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
            $this->_data['photo'] = Photo::find($id);
            return view('photo.edit')->with('photos',$this->_data);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
            //Get Input
            $input = Input::all();

            if ($this->photo->isValid($input)) {
                $mime = $input['file']->getMimeType();
                $fileName = time() . "." . strtolower($input['file']->getClientOriginalExtension());

                $photo = Photo::find($id);
                $photo->title = Input::get('title');

                //Delete Old from Bucket
                $s3 = AWS::get('s3');
                $s3->deleteObject(array('Bucket' => 'anglyeds','Key' => "resource/{$photo->file}"));
                $s3->deleteObject(array('Bucket' => 'anglyeds','Key' => "resource/{$photo->file}"));

                //Upload new files
                $image = Image::make($input['file']->getRealPath());
                $this->upload_s3($image, $fileName, $mime, "resource");
                $image->resize(400, 300);
                $this->upload_s3($image, $fileName, $mime, "resource");

                $photo->file = $fileName;
                $photo->save();

                return Redirect::route('photo.index');
            } else {
                Session::flash('error', 'Se ha producido un error al editar la imagen');
                return Redirect::back()->withInput()->withErrors($this->photo->messages);
            }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
            $photo = Photo::find($id);
            
            //Delete object from S3 Bucket
            $s3 = AWS::get('s3');
            $s3->deleteObject(array('Bucket' => 'anglyeds','Key' => "resource/{$photo->file}"));
            $s3->deleteObject(array('Bucket' => 'anglyeds','Key' => "resource/{$photo->file}"));
                
            $photo->delete();
            return Redirect::route('photo.index');
    }
        
        private function upload_s3($image, $fileName, $mime, $folder) {
            $s3 = AWS::createClient('s3');
            $s3->putObject(array(
                'Bucket'     => 'anglyeds',
                'Key'        => "{$folder}/{$fileName}",
                'Body'       => "$image",
                'ContentType' => $mime,
            ));
        }

        private $_data = array();
        private $path = "img/upload/";
}
