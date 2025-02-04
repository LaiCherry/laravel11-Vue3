<template>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3>Dashboard</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">You are logged in as <b>{{ user?.data?.name }}</b></p>
                        <p class="mb-0">You Detail is below<br/> <b>{{ userData }}</b></p>
                        <p class="mb-0">Hello {{ vais }}<br/></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import store from "../store"
export default {
    name:"dashboard",
    data(){
        return {
            user:[],
            userData:[],
            // vais: store.state.isLogin
        }
    },
    async mounted() {
        // this.user = JSON.parse(localStorage.getItem('user'))
        // let token = this.user?.data.token;
        // const authHeader = {
        //     'Authorization': 'Bearer ' + token,
        //     'X-REQUEST-TYPE': 'axios'
        // }
        // let config = {headers:authHeader}
        await axios.get('/api/user1').then(({data})=>{
                this.userData=data;
                // console.log(data)
                if(data) {
                    store.dispatch('setIsLogin', true)
                    // console.log(store.state.isLogin)
                    router.push({name:'dashboard'})
                } else { store.dispatch('setIsLogin', false) }
            }).catch(({response})=>{
                if(response){
                    this.validationErrors = {}
                    store.dispatch('setIsLogin', false)
                    // console.log(store.state.isLogin)
                    alert(response?.data.message)
                }
            })
    },
    computed: {
        vais () {
            return store.state.isLogin
        }
        // userData () {
        //     console.log('12345677')
        //     if(this.userData != [])
        //     {
        //         store.dispatch('setIsLogin', true)
        //         console.log(store.state.isLogin)
        //     } else {
        //         store.dispatch('setIsLogin', false)
        //         console.log(store.state.isLogin)
        //     }  
        // }
    }
}
</script>
