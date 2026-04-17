import controller_0 from "../ux-turbo/turbo_controller.js";
import controller_1 from "../../controllers/hello_controller.js";
import controller_2 from "../../controllers/street_tilt_controller.js";
import controller_3 from "../../controllers/home_quantity_controller.js";
export const eagerControllers = {"symfony--ux-turbo--turbo-core": controller_0, "hello": controller_1, "street-tilt": controller_2, "home-quantity": controller_3};
export const lazyControllers = {"csrf-protection": () => import("../../controllers/csrf_protection_controller.js")};
export const isApplicationDebug = true;