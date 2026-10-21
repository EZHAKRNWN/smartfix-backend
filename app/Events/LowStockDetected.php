namespace App\Events;

use App\Models\Part;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public $part;

    public function __construct(Part $part)
    {
        $this->part = $part;
    }
}