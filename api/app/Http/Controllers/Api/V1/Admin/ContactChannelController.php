<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContactChannelRequest;
use App\Http\Requests\Admin\UpdateContactChannelRequest;
use App\Http\Resources\Admin\ContactChannelResource;
use App\Models\ContactChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContactChannelController extends Controller
{
    public function store(StoreContactChannelRequest $request): JsonResponse
    {
        $channel = ContactChannel::create($request->validated());

        return (new ContactChannelResource($channel->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateContactChannelRequest $request, ContactChannel $contactChannel): JsonResponse
    {
        $contactChannel->update($request->validated());

        return (new ContactChannelResource($contactChannel->fresh()))->response();
    }

    public function destroy(ContactChannel $contactChannel): Response
    {
        $contactChannel->delete();

        return response()->noContent();
    }
}
