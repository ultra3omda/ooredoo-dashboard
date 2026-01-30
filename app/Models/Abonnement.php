<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Abonnement extends Model
{
    use HasFactory;

    protected $table = 'abonnement';
    protected $primaryKey = 'abonnement_id';
    public $timestamps = false;

    protected $fillable = [
        'abonnement_nom',
        'abonnement_icon',
        'abonnement_desc',
        'abonnement_duree',
        'country_id',
        'abonnement_store',
        'entry_by',
        'tarif_year',
        'tarif_day',
        'features',
        'tag',
        'tag_color',
        'tag_bg_color',
        'is_featured',
        'sub_advantage',
        'sub_advantage_title',
        'sub_advantage_link',
        'sub_advantage_image',
        'sub_advantage_condition',
        'sub_advantage_description',
        'sub_advantage_features',
        'sub_order',
        'title_sub_advantage_link',
    ];

    public function tarifs()
    {
        return $this->hasMany(AbonnementTarif::class, 'abonnement_id', 'abonnement_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }
}