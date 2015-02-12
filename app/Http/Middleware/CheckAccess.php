<?php namespace MyBB\Core\Http\Middleware;

use MyBB\Core\Database\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Closure;

class CheckAccess {

    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($this->checkPermissions($request))
        {
            return $next($request);
        }

        return "<h1>Error No Permission</h1>";
    }

    /**
     *  Check Permissions
     *
     *  @param  \Illuminate\Http\Request  $request
     *  @return Boolean True if permission check passes, false otherwise
     */
    protected function checkPermissions($request)
    {
        $action = $request->route()->getAction();
        // Check for additional permissions required
        $requiredPermisions = isset($action['permissions']) ? explode('|', $action['permissions']) : false;

        // Weed out the Guests first
        if(!$this->auth->user())
        {
            // Is this Route Guest Only
            if(isset($action['except']) && $action['except'] == 'guest')
                return false;

            if(!$requiredPermisions)
                return true;
        }

        // Check if route is protected
        if(isset($action['except']))
        {
            // Check if our role is allowed
            $notAllowed = explode('|', $action['except']);

            if(in_array($this->auth->user()->role->role_slug, $notAllowed))
            {
                return false;
            }
        }

        // Did we get this far without a false. This is the final check.

        return $this->auth->user()->canAccess($requiredPermisions);
    }
}
