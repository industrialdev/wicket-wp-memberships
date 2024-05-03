import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Spinner, Icon } from '@wordpress/components';
import { PLUGIN_API_URL } from '../constants';

const MemberEdit = ({ memberType }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [member, setMember] = useState(null);

  useEffect(() => {
  }, []);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{memberType === 'individual' ? __('Individual Members', 'wicket-memberships') : __('Organization Members', 'wicket-memberships')}
				</h1>
				<hr className="wp-header-end"></hr>

        Hello!
        {memberType}

			</div>
		</>
	);
};

const app = document.getElementById('edit_member');
if (app) {
	createRoot(app).render(<MemberEdit {...app.dataset} />);
}