import styled from 'styled-components';
import { Flex, Disabled } from '@wordpress/components';
import Select from 'react-select'

export const CustomDisabled = styled(Disabled)`
	opacity: .5;
`;

export const Wrap = styled.div`
	max-width: 600px;
`;

export const ActionRow = styled.div`
	margin-top: 30px;
`;

export const FormFlex = styled(Flex)`
	margin-top: 15px;

	@media screen and (max-width: 767px) {
		align-items: normal !important;
	}
`;

export const ErrorsRow = styled.div`
	padding: 10px 0;
	margin-left: -15px;
`;

export const BorderedBox = styled.div`
	border: 1px solid #c3c4c7;
	padding: 15px;
	margin-top: 15px;
`;

export const SelectWpStyled = styled(Select)`
	.select__input-container {
		margin: 0;
		padding: 0;
	}

	.select__dropdown-indicator {
		padding: 0 4px;
	}

	.select__control {
		border: 1px solid #949494;
    border-radius: 2px;
		min-height: 28px;
	}

	.select__input {
		min-height: 28px;
		box-shadow: none !important;
	}

	.select__value-container {
		padding: 0 8px;
	}

	.select__value-container--is-multi {
		padding: 6px 8px;
	}
`;

export const LabelWpStyled = styled.label`
	font-size: 11px;
	font-weight: 500;
	line-height: 1.4;
	text-transform: uppercase;
	display: inline-block;
	margin-bottom: 8px;
	padding: 0px;
`;