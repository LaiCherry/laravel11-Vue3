import { createStore, mapGetters } from "vuex";

const store = createStore({
    state: {
        test: '1234',
        isLogin: false,
    },
    getters: {},
    actions: {
        setIsLogin ({ commit }, boolean) {
            commit('setIsLogin', boolean)
        },
    },
    mutations: {
        setIsLogin (state, boolean){
            state.isLogin = boolean
        }
    },
})

export default store