<?php
/**
 * @author    MyBB Group
 * @version   2.0.0
 * @package   mybb/core
 * @license   http://www.mybb.com/licenses/bsd3 BSD-3
 */

namespace MyBB\Core\Presenters;

use Illuminate\Contracts\Auth\Guard;
use McCool\LaravelAutoPresenter\BasePresenter;
use MyBB\Core\Database\Models\ProfileField as ProfileFieldModel;
use MyBB\Core\Database\Models\User;
use MyBB\Core\Database\Repositories\ProfileFieldOptionRepositoryInterface;
use MyBB\Core\Database\Repositories\UserProfileFieldRepositoryInterface;
use MyBB\Core\Form\RenderableInterface;

class ProfileFieldPresenter extends BasePresenter implements RenderableInterface
{
    /**
     * @var Guard
     */
    protected $guard;

    /**
     * @var UserProfileFieldRepositoryInterface
     */
    protected $userProfileFields;

    /**
     * @var ProfileFieldOptionRepositoryInterface
     */
    protected $profileFieldOptionRepository;

    /**
     * @param ProfileFieldModel $resource
     * @param Guard $guard
     * @param UserProfileFieldRepositoryInterface $userProfileFields
     * @param ProfileFieldOptionRepositoryInterface $profileFieldOptionRepository
     */
    public function __construct(
        ProfileFieldModel $resource,
        Guard $guard,
        UserProfileFieldRepositoryInterface $userProfileFields,
        ProfileFieldOptionRepositoryInterface $profileFieldOptionRepository
    ) {
        parent::__construct($resource);

        $this->guard = $guard;
        $this->userProfileFields = $userProfileFields;
        $this->profileFieldOptionRepository = $profileFieldOptionRepository;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $options = $this->profileFieldOptionRepository->getForProfileField($this->getWrappedObject());

        $formattedOptions = [];

        foreach ($options as $option) {
            $formattedOptions[$option->getValue()] = $option->getName();
        }

        return $formattedOptions;
    }

    /**
     * @return array
     */
    public function options()
    {
        return $this->getOptions();
    }

    /**
     * @return string
     */
    public function getElementName()
    {
        return 'profile_fields[' . $this->id . ']';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->name;
    }

    /**
     * @param User $user
     *
     * @return mixed
     */
    public function getValue(UserPresenter $user = null)
    {
        if ($user == null) {
            $user = $this->guard->user();
        } else {
            $user = $user->getWrappedObject();
        }

        $userProfileField = $this->userProfileFields->findForProfileField($user, $this->getWrappedObject());

        if ($userProfileField) {
            return $userProfileField->getValue();
        }

        return null;
    }

    /**
     * @return bool
     */
    public function has_value(UserPresenter $user)
    {
        return $this->userProfileFields->hasForProfileField($user->getWrappedObject(), $this->getWrappedObject());
    }

    /**
     * @return array
     */
    public function getValidationRules()
    {
        return $this->validation_rules;
    }

    /**
     * @return bool
     */
    public function has_validation_rules()
    {
        return (bool)$this->getValidationRules();
    }
}
