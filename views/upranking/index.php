<?php
$this->title = 'UpRanking';
?>

<div id="app">
    <template>
        <el-table id="tableData" :data="tableData" :cell-style="cellStyle" :span-method="objectSpanMethod" :row-class-name="tableRowClassName" :height="tableHeight" border style="width: 100%">
            <el-table-column prop="code" label="code">
            </el-table-column>
            <el-table-column prop="rise" label="rise">
            </el-table-column>
            <el-table-column prop="name" label="name">
            </el-table-column>
            <el-table-column prop="price" label="price">
            </el-table-column>
            <el-table-column prop="up" label="up">
            </el-table-column>
            <el-table-column prop="upp" label="upp">
            </el-table-column>
            <el-table-column prop="zt" label="zt">
            </el-table-column>
            <el-table-column prop="dt" label="dt">
            </el-table-column>
            <el-table-column prop="cjs" label="cjs">
            </el-table-column>
            <el-table-column prop="cje" label="cje">
            </el-table-column>
            <el-table-column prop="zf" label="zf">
            </el-table-column>
            <el-table-column prop="zg" label="zg">
            </el-table-column>
            <el-table-column prop="zd" label="zd">
            </el-table-column>
            <el-table-column prop="jk" label="jk">
            </el-table-column>
            <el-table-column prop="zs" label="zs">
            </el-table-column>
            <el-table-column prop="lb" label="lb">
            </el-table-column>
            <el-table-column prop="hsl" label="hsl">
            </el-table-column>
            <el-table-column prop="syl" label="syl">
            </el-table-column>
            <el-table-column prop="sjl" label="sjl">
            </el-table-column>
            <el-table-column prop="date" label="date">
            </el-table-column>
            <el-table-column prop="dif" label="dif">
            </el-table-column>
            <el-table-column prop="dea" label="dea">
            </el-table-column>
            <el-table-column prop="macd" label="macd">
            </el-table-column>
            <el-table-column prop="type" label="type">
            </el-table-column>
        </el-table>
    </template>
</div>

<style>
    .el-table .curdate-row {
        background: SNOW;
    }
</style>

<script>
    new Vue({
        el: '#app',
        data: {
            tableHeight: 999,
            tableData: [],
            curDate: "",
            timer: null,
            refreshCode: []
        },
        created() {
            this.getCurDate();
            this.getData();
        },
        mounted() {
            this.getAutoHeight();
            const self = this;
            window.onresize = function() {
                self.getAutoHeight();
            };
        },
        destroyed: function() {
            clearInterval(this.timer);
            this.timer = null;
        },
        methods: {
            getData() {
                let self = this;
                this.$axios({
                    method: "get",
                    url: "https://api.ningle.info/upranking",
                    data: {},
                }).then((response) => {
                    if (0 === response.data.code) {
                        self.tableData = response.data.list;
                        response.data.list.forEach(element => {
                            if (self.curDate == element.date) {
                                self.refreshCode.push(element.code);
                            }
                        });
                        // this.timer = setInterval(function () {

                        // }, 9988);
                    }
                });

                console.log(this.refreshCode);
            },
            getAutoHeight() {
                this.$nextTick(() => {
                    this.tableHeight = window.innerHeight - 18.99;
                });
            },
            objectSpanMethod({
                row,
                column,
                rowIndex,
                columnIndex
            }) {
                if ("code" === column.property || "type" === column.property) {
                    if (rowIndex % 9 === 0) {
                        return {
                            rowspan: 9,
                            colspan: 1,
                        };
                    } else {
                        return {
                            rowspan: 0,
                            colspan: 0,
                        };
                    }
                }
            },
            cellStyle({
                row,
                column,
                rowIndex,
                columnIndex
            }) {
                var color = "";
                switch (column.property) {
                    case "up":
                        if (row.price === row.zt) color = "RED";
                        else if (row.price === row.dt) color = "GREEN";
                        else if (19.99 <= row.up) color = "CRIMSON";
                        break;
                    case "zg":
                        if (row.zg === row.zt) color = "RED";
                        break;
                    case "zd":
                        if (row.zd === row.dt) color = "GREEN";
                        break;
                    case "zf":
                        if (0 == row.zf) color = "HOTPINK";
                        else if (9.99 <= row.zf && 19.99 >= row.zf) color = "DEEPPINK";
                        else if (19.99 <= row.zf) color = "MEDIUMVIOLETRED";
                        break;
                }
                return "color: " + color;
            },
            tableRowClassName({
                row,
                rowIndex
            }) {
                if (this.curDate === row.date) {
                    return "curdate-row";
                }
                return "";
            },
            getCurDate() {
                var now = new Date();
                var month = now.getMonth() + 1;
                var day = now.getDate();
                if (month < 10) month = "0" + month;
                if (day < 10) day = "0" + day;
                this.curDate = now.getFullYear() + "-" + month + "-" + day;
            },
        }
    })
</script>