<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GalleryPhoto extends Model
{
    protected $guarded = ['id'];
    public function event() { return $this->belongsTo(GalleryEvent::class, 'gallery_event_id'); }
    public function scopeCoverPhoto($query)
    {
        return $query->where('gallery_photos.id', function ($subquery) {
            $subquery->select('cover.id')->from('gallery_photos as cover')
                ->whereColumn('cover.gallery_event_id', 'gallery_photos.gallery_event_id')
                ->orderBy('cover.id')->limit(1);
        });
    }
}
