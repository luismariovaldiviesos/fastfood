<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;


    protected $fillable = ['name'];


    //reglas de validacion
    public static function rules($id)
    {
        // registro nuevo
        if($id <= 0)
        {
          return ['name' => 'required|min:3|max:50|unique:categories'];
        }
        //actualizar registro
        else
        {
           return  ['name' => "required|min:3|max:50|unique:categories,name,{$id}"];
        }
    }

    public static $messages = [
        'name.required' => 'el nombre de la categoria es requerido',
        'name.min' => 'el nombre de la categoria debe tener al menos 3 caracteres',
        'name.max' => 'el nombre de la categoria debe tener maximo 50 caracteres',
        'name.unique' => 'el nombre de categoria ya existe en el sistema',
    ];


    // relaciones

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function image()
    {
        return $this->morphOne(Image::class, 'model')->withDefault(); // si no hay imagen devuelve vacio
    }


    //muttators && accesors

    public function getImgAttribute()
    {
        $img = $this->image->file;
        if($img != null)
        {
            if (file_exists('storage/categories' . $img))
                return 'storage/categories' . $img;
                else
                return 'storage/image-not-found.png';
        }

        return 'storage/noimg.png';
    }

}
