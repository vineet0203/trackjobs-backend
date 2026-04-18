<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Api\V1\Customers\CustomerLoginRequest;
use App\Http\Requests\Api\V1\Customers\CustomerSetPasswordRequest;
use App\Services\Customer\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAuthController extends BaseController
{
    public function __construct(private CustomerAccountService $customerAccountService) {}

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $this->customerAccountService->login($validated['email'], $validated['password']);

            return $this->successResponse($data, 'Customer login successful.');
        } catch (HttpException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Customer login failed.', 500);
        }
    }

    public function setPassword(CustomerSetPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $this->customerAccountService->setPassword($validated['email'], $validated['token'], $validated['password']);

            return $this->successResponse(null, 'Password set successfully. You can now log in.');
        } catch (HttpException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Failed to set password.', 500);
        }
    }

    public function me(): JsonResponse
    {
        $customer = request()->attributes->get('customer');

        return $this->successResponse($customer, 'Customer authenticated successfully.');
    }
}