<template>
    <div>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="https://polinwei.com/laravel-11-with-sanctum/" target="_blank">PolinWEI Blog</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavDropdown">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <router-link :to="{name:'dashboard'}" class="nav-link">Home <span class="sr-only">(current)</span></router-link>
                        </li>
                    </ul>
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <router-link :to="{name:'dashboard1'}" class="nav-link">About</router-link>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {{ user?.data?.name }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownMenuLink">
                                    <a class="dropdown-item" href="javascript:void(0)" @click="logout">Logout</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        <main class="mt-3">
            <router-view></router-view>
        </main>
    </div>
</template>

<script>
import router from '@/router'
export default {
    name:"default-layout",
    data(){
        return {
            user:[]
        }
    },
    mounted() {
        this.user = JSON.parse(localStorage.getItem('user'))
    },
    methods:{
        async logout(){
            await axios.post('/api/logout',this.user).then(response=>{
                if(response.data.data.logout){
                    localStorage.clear()
                    this.validationErrors = {}
                    window.location.href="/"
                    alert(response.data.message)
                } else { alert(response.data.message) }
            }).catch(({response})=>{
                if(response.status===422){
                    this.validationErrors = response.data.errors
                }else{
                    this.validationErrors = {}
                    alert(response.data.message)
                }
            })
        }
    }
}
</script>