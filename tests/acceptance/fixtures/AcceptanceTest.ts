import { mergeTests, test as ShopwellTestSuite } from '@shopwell-ag/acceptance-test-suite';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as HomeProducts } from './HomeProducts';
import { test as VisualTestSetup } from './VisualTestSetup';

export * from '@shopwell-ag/acceptance-test-suite';

export const test = mergeTests(ShopwellTestSuite, shopCustomerTasks, shopAdminTasks, HomeProducts, VisualTestSetup);
