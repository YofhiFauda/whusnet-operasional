use App\Models\Customer; use Illuminate\Support\Facades\DB;
$c = Customer::with(['customerService','pop'])->whereIn('cid',['D00RQ000010','D00RQ000014'])->get();
foreach ($c as $x) {
  echo "{$x->id} | {$x->cid} | {$x->customer_code} | {$x->full_name} | pop=".$x->pop?->name." | status={$x->status} | created={$x->created_at} | updated={$x->updated_at} | legacy_id=".($x->legacy_customer_id ?? $x->old_customer_id ?? '-')." | svc_status=".($x->customerService?->service_status ?? '-')." | activation=".($x->customerService?->activation_date ?? '-')."\n";
}
echo "== distribusi status antrian survey per pop ==\n";
foreach (DB::table('customers as c')->join('pops as p','p.id','=','c.pop_id')->where('c.status','waiting_survey')->select('p.name',DB::raw('count(*) n'),DB::raw('min(c.created_at) mn'),DB::raw('max(c.created_at) mx'))->groupBy('p.name')->get() as $r) echo "{$r->name} = {$r->n} ({$r->mn} .. {$r->mx})\n";
echo "== status history D00RQ000010 ==\n";
$id = $c->firstWhere('cid','D00RQ000010')?->id;
if ($id && \Illuminate\Support\Facades\Schema::hasTable('customer_status_histories')) {
  foreach (DB::table('customer_status_histories')->where('customer_id',$id)->orderBy('id')->get() as $h) echo json_encode($h)."\n";
}
echo "== kolom customers ==\n".implode(',', \Illuminate\Support\Facades\Schema::getColumnListing('customers'))."\n";
