<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property bool $phone_verified
 */

/**
 * @OA\Schema(
 *    schema="ProfileSchema",
 *
 *    @OA\Property(
 *        property="id",
 *        type="integer",
 *        description="User ID",
 *        nullable=false,
 *        example="1"
 *    ),
 *    @OA\Property(
 *        property="email",
 *        type="string",
 *        description="User EMail",
 *        nullable=false,
 *        format="email",
 *        example="john.doe@inbox.com"
 *    ),
 *    @OA\Property(
 *        property="phone",
 *        @OA\Property(
 *          property="number",
 *          type="string",
 *          description="User phone number",
 *          nullable=true,
 *          example="1234567890"
 *        ),
 *        @OA\Property(
 *          property="verified",
 *          type="boolean",
 *          description="Is phone number verified?",
 *          nullable=false,
 *          example="1"
 *        )
 *    ),
 *    @OA\Property(
 *        property="name",
 *        @OA\Property(
 *          property="first",
 *          type="string",
 *          description="User First Name",
 *          nullable=false,
 *          example="John"
 *        ),
 *        @OA\Property(
 *          property="last",
 *          type="string",
 *          description="User Last Name",
 *          nullable=true,
 *          example="Doe"
 *        )
 *    ),
 * )
 */
class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => [
                'number' => $this->phone,
                'verified' => $this->phone_verified,
            ],
            'name' => [
                'first' => $this->name,
                'last' => $this->last_name,
            ],
        ];
    }
}
