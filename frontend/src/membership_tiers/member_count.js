import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Spinner } from '@wordpress/components';
import { PLUGIN_API_URL } from '../constants';

const MembershipTierCount = ({ tierUuid }) => {

	const [memberCount, setMemberCount] = useState(null);

	useEffect(() => {

    apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: [tierUuid]
      },
      'properties[]': 'count'
    }) }).then((tiersInfo) => {

      setMemberCount( tiersInfo.tier_data[tierUuid].count );
		}).catch((error) => {
      console.log('Tier Info Error:');
      console.log(error);
		});

	}, []);

	return (
		<>
      {memberCount === null && <Spinner />}
      {memberCount !== null && memberCount}
		</>
	);
};

// init multiple instances
const app = document.querySelectorAll('.wicket_memberships_tier_cell_member_count');
if (app) {
  app.forEach((el) => {
    createRoot(el).render(<MembershipTierCount {...el.dataset} />);
  });
}