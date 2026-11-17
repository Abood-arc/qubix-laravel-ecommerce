<?php

namespace DigitalLabs\Admin\Http\Controllers\Sales;

use DigitalLabs\Admin\DataGrids\Sales\OrderRefundDataGrid;
use DigitalLabs\Admin\Http\Controllers\Controller;
use DigitalLabs\Sales\Exceptions\InvalidRefundQuantityException;
use DigitalLabs\Sales\Repositories\OrderItemRepository;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Sales\Repositories\RefundRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class RefundController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderItemRepository $orderItemRepository,
        protected RefundRepository $refundRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(OrderRefundDataGrid::class)->process();
        }

        return view('admin::sales.refunds.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create(int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        return view('admin::sales.refunds.create', compact('order'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * A double-clicked Refund button sends two requests that would both pass the checks
     * before either is saved, refunding twice. Refunds for one order therefore run one at a
     * time: the second waits, then re-checks against what the first refunded.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(int $orderId)
    {
        try {
            return Cache::lock('admin-refund:order:'.$orderId, 60)
                ->block(20, fn () => $this->storeRefund($orderId));
        } catch (LockTimeoutException) {
            session()->flash('error', trans('admin::app.sales.refunds.create.in-progress'));

            return redirect()->back();
        }
    }

    /**
     * Check and create one refund; runs while holding the order's refund lock.
     *
     * @return \Illuminate\Http\Response
     */
    protected function storeRefund(int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        if (! $order->canRefund()) {
            session()->flash('error', trans('admin::app.sales.refunds.create.creation-error'));

            return redirect()->back();
        }

        $this->validate(request(), [
            'refund.items' => 'array',
            'refund.items.*' => 'required|numeric|min:0',
        ]);

        $data = request()->all();

        if (! isset($data['refund']['shipping'])) {
            $data['refund']['shipping'] = 0;
        }

        try {
            $totals = $this->refundRepository->getOrderItemsRefundSummary($data['refund'], $orderId);

            if (! $totals) {
                throw new InvalidRefundQuantityException(trans('admin::app.sales.refunds.create.invalid-qty'));
            }
        } catch (InvalidRefundQuantityException $invalidRefundQuantityException) {
            session()->flash('error', $invalidRefundQuantityException->getMessage());

            return redirect()->back();
        }

        $maxRefundAmount = $totals['grand_total']['price'] - $order->refunds()->sum('base_adjustment_refund');

        $refundAmount = $totals['grand_total']['price'] - $totals['shipping']['price'] + $data['refund']['shipping'] + $data['refund']['adjustment_refund'] - $data['refund']['adjustment_fee'];

        if (! $refundAmount) {
            session()->flash('error', trans('admin::app.sales.refunds.create.invalid-refund-amount-error'));

            return redirect()->back();
        }

        if ($refundAmount > $maxRefundAmount) {
            session()->flash('error', trans('admin::app.sales.refunds.create.refund-limit-error', [
                'amount' => core()->formatBasePrice($maxRefundAmount),
            ]));

            return redirect()->back();
        }

        $this->refundRepository->create(array_merge($data, ['order_id' => $orderId]));

        session()->flash('success', trans('admin::app.sales.refunds.create.create-success'));

        return redirect()->route('admin.sales.orders.view', $orderId);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function updateTotals(int $orderId)
    {
        try {
            $data = $this->refundRepository->getOrderItemsRefundSummary(request()->input(), $orderId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json($data);
    }

    /**
     * Show the view for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function view($id)
    {
        $refund = $this->refundRepository->findOrFail($id);

        return view('admin::sales.refunds.view', compact('refund'));
    }
}
